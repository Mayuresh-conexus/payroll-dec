<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHolidayRequest;
use App\Http\Requests\UpdateHolidayRequest;
use App\Models\Holiday;
use App\Models\PayrollRun;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class HolidayController extends Controller
{
    public function index(): View
    {
        $holidays = Holiday::with('creator')
            ->orderByDesc('start_date')
            ->paginate(15);

        // Which payroll weeks each holiday lands in, so the list can show when a
        // change would disturb a week that has already been calculated.
        $affected = collect($holidays->items())
            ->mapWithKeys(fn (Holiday $holiday): array => [
                $holiday->id => $this->runsForHoliday($holiday),
            ]);

        return view('holidays.index', compact('holidays', 'affected'));
    }

    public function store(StoreHolidayRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $data['created_by'] = auth()->id();

        $holiday = Holiday::create($data);

        return back()->with('success', $this->successMessage("Holiday \"{$holiday->name}\" added.", $holiday));
    }

    public function edit(Holiday $holiday): RedirectResponse
    {
        // Used for inline modal — not a separate page, redirect to index with modal
        return redirect()->route('holidays.index');
    }

    public function update(UpdateHolidayRequest $request, Holiday $holiday): RedirectResponse
    {
        // Check both the old and new range: moving a holiday off a finalised week
        // rewrites that week's pay just as much as moving one onto it.
        if ($blocked = $this->finalisedWeekBlocker($holiday, $request->validated())) {
            return back()->withErrors(['holiday' => $blocked])->withInput();
        }

        $holiday->update($request->validated());

        return back()->with('success', $this->successMessage("Holiday \"{$holiday->name}\" updated.", $holiday));
    }

    public function destroy(Holiday $holiday): RedirectResponse
    {
        if ($blocked = $this->finalisedWeekBlocker($holiday)) {
            return back()->withErrors(['holiday' => $blocked]);
        }

        $name = $holiday->name;
        $affected = $this->runsForHoliday($holiday);

        $holiday->delete();

        return back()->with('success', $this->refreshHint("Holiday \"{$name}\" deleted.", $affected));
    }

    /**
     * The weekly payroll runs covering any day of a holiday.
     *
     * @return Collection<int, PayrollRun>
     */
    private function runsForHoliday(Holiday $holiday, ?array $override = null): Collection
    {
        $start = Carbon::parse($override['start_date'] ?? $holiday->start_date);
        $end = Carbon::parse($override['end_date'] ?? $holiday->end_date);

        $weeks = [];
        $cursor = $start->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $weeks[$cursor->isoWeekYear.'-'.$cursor->isoWeek] = [
                'year' => $cursor->isoWeekYear,
                'week' => $cursor->isoWeek,
            ];
            $cursor->addDay();
        }

        if ($weeks === []) {
            return collect();
        }

        return PayrollRun::where('period_type', 'weekly')
            ->where(function ($query) use ($weeks): void {
                foreach ($weeks as $week) {
                    $query->orWhere(fn ($q) => $q->where('year', $week['year'])->where('week_number', $week['week']));
                }
            })
            ->orderBy('year')
            ->orderBy('week_number')
            ->get();
    }

    /**
     * Refuse the change when it would alter a week that has been signed off.
     */
    private function finalisedWeekBlocker(Holiday $holiday, ?array $override = null): ?string
    {
        $runs = $this->runsForHoliday($holiday)
            ->merge($override ? $this->runsForHoliday($holiday, $override) : collect())
            ->unique('id')
            ->filter(fn (PayrollRun $run): bool => $run->status === 'final');

        if ($runs->isEmpty()) {
            return null;
        }

        $weeks = $runs->map(fn (PayrollRun $run): string => "week {$run->week_number} of {$run->year}")->implode(', ');

        return "This holiday falls in finalised payroll ({$weeks}). Revert those weeks to draft first if the holiday really needs to change.";
    }

    private function successMessage(string $message, Holiday $holiday): string
    {
        return $this->refreshHint($message, $this->runsForHoliday($holiday));
    }

    /**
     * Holidays change what a week recalculates to, but nothing is recomputed here —
     * refreshing a week also resets cash/bank splits, so that stays the admin's call.
     *
     * @param  Collection<int, PayrollRun>  $runs
     */
    private function refreshHint(string $message, Collection $runs): string
    {
        $drafts = $runs->filter(fn (PayrollRun $run): bool => $run->status !== 'final');

        if ($drafts->isEmpty()) {
            return $message;
        }

        $weeks = $drafts->map(fn (PayrollRun $run): string => "week {$run->week_number}")->implode(', ');

        return $message." Refresh {$weeks} on the weekly payroll page to apply it.";
    }
}
