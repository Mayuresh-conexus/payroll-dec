<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreLeaveRequest;
use App\Http\Requests\UpdateLeaveRequest;
use App\Models\Employee;
use App\Models\Leave;
use App\Models\PayrollRun;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class LeaveController extends Controller
{
    public function __construct(private LeaveService $leaveService) {}

    public function index(): View
    {
        $leaves = Leave::with(['employee', 'creator'])
            ->orderByDesc('start_date')
            ->paginate(15);

        // Which payroll weeks each spell of leave lands in, so the list can show
        // when a change would disturb a week that has already been calculated.
        $affected = collect($leaves->items())
            ->mapWithKeys(fn (Leave $leave): array => [
                $leave->id => $this->runsForRange($leave->start_date, $leave->end_date),
            ]);

        $employees = Employee::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        // One query for the whole table rather than two per employee.
        $this->leaveService->preloadLeaveFor($employees);

        $balances = $employees->mapWithKeys(fn (Employee $employee): array => [
            $employee->id => $this->leaveService->balanceFor($employee),
        ]);

        return view('leaves.index', compact('leaves', 'affected', 'employees', 'balances'));
    }

    public function store(StoreLeaveRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $employee = Employee::findOrFail($data['employee_id']);

        // Creating leave inside a signed-off week would add pay to it after the
        // fact, so it is refused for the same reason a change to one is.
        if ($blocked = $this->finalisedWeekBlocker($data['start_date'], $data['end_date'])) {
            return back()->withErrors(['leave' => $blocked])->withInput();
        }

        $data['hours_per_day'] = $this->hoursPerDayFor($employee, $data['hours_per_day'] ?? null);
        $data['created_by'] = auth()->id();

        $leave = Leave::create($data);

        return back()->with('success', $this->successMessage("Leave for {$employee->name} added.", $leave));
    }

    public function update(UpdateLeaveRequest $request, Leave $leave): RedirectResponse
    {
        $data = $request->validated();
        $employee = Employee::findOrFail($data['employee_id']);

        // Check the old range as well as the new one: moving leave off a finalised
        // week rewrites that week's pay just as much as moving it onto one.
        $blocked = $this->finalisedWeekBlocker($leave->start_date, $leave->end_date)
            ?? $this->finalisedWeekBlocker($data['start_date'], $data['end_date']);

        if ($blocked) {
            return back()->withErrors(['leave' => $blocked])->withInput();
        }

        $data['hours_per_day'] = $this->hoursPerDayFor($employee, $data['hours_per_day'] ?? null);

        $leave->update($data);

        return back()->with('success', $this->successMessage("Leave for {$employee->name} updated.", $leave));
    }

    public function destroy(Leave $leave): RedirectResponse
    {
        if ($blocked = $this->finalisedWeekBlocker($leave->start_date, $leave->end_date)) {
            return back()->withErrors(['leave' => $blocked]);
        }

        $name = $leave->employee?->name ?? 'employee';
        $affected = $this->runsForRange($leave->start_date, $leave->end_date);

        $leave->delete();

        return back()->with('success', $this->refreshHint("Leave for {$name} deleted.", $affected));
    }

    /**
     * The hours one leave day pays.
     *
     * Daily-rate staff are paid whole days, so they never carry a figure. Hourly
     * staff default to their standard day, which the admin can override for a
     * short day.
     */
    private function hoursPerDayFor(Employee $employee, mixed $submitted): ?float
    {
        if ($employee->type !== 'hourly') {
            return null;
        }

        return $submitted !== null && $submitted !== ''
            ? (float) $submitted
            : (float) ($employee->hours_per_day ?? 0);
    }

    /**
     * The weekly payroll runs covering any day of a date range.
     *
     * @return Collection<int, PayrollRun>
     */
    private function runsForRange(mixed $start, mixed $end): Collection
    {
        $cursor = Carbon::parse($start)->startOfDay();
        $last = Carbon::parse($end)->startOfDay();

        $weeks = [];

        while ($cursor->lte($last)) {
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
    private function finalisedWeekBlocker(mixed $start, mixed $end): ?string
    {
        $runs = $this->runsForRange($start, $end)
            ->filter(fn (PayrollRun $run): bool => $run->status === 'final');

        if ($runs->isEmpty()) {
            return null;
        }

        $weeks = $runs->map(fn (PayrollRun $run): string => "week {$run->week_number} of {$run->year}")->implode(', ');

        return "This leave falls in finalised payroll ({$weeks}). Revert those weeks to draft first if the leave really needs to change.";
    }

    private function successMessage(string $message, Leave $leave): string
    {
        return $this->refreshHint($message, $this->runsForRange($leave->start_date, $leave->end_date));
    }

    /**
     * Leave changes what a week recalculates to, but nothing is recomputed here —
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
