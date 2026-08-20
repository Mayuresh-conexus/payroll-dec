<?php

namespace App\Services;

use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HourlyAttendance;
use Carbon\Carbon;

/**
 * Bank-holiday double pay, accumulated over a month and settled in one week.
 *
 * Holidays are earned on the day they are worked, but the office pays them out
 * once a month: every holiday worked between the 1st and the last day of the
 * month is cleared in the ISO week containing that last day. Every other week
 * shows nothing, which is why the payroll table hides the columns entirely
 * outside the settlement week.
 *
 * Working the day pays double, so the premium is a second helping of what the
 * day already earned — one day's rate for daily staff, every hour worked
 * (overtime included) for hourly staff.
 */
class BankHolidayService
{
    /** Weekday keys in ISO order, so the index is the offset from the week's Monday. */
    private const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * Holiday dates already looked up, keyed by month.
     *
     * Every employee on a payroll week asks for the same month's holidays, so
     * without this the list is re-queried once per person. Holidays cannot
     * change while a page is being rendered, which is what makes it safe to
     * hold — attendance, which does change mid-request, is never cached here.
     *
     * @var array<string, list<Carbon>>
     */
    private array $holidayDates = [];

    /**
     * The month a given payroll week settles, or null for an ordinary week.
     *
     * A month is settled by the week containing its final day, so each month has
     * exactly one settlement week and no week ever settles two months.
     */
    public function settledMonth(int $year, int $week): ?Carbon
    {
        $weekStart = Carbon::now()->setISODate($year, $week, 1)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();
        $monthEnd = $weekStart->copy()->endOfMonth();

        return $monthEnd->between($weekStart, $weekEnd) ? $weekStart->copy()->startOfMonth() : null;
    }

    public function isSettlementWeek(int $year, int $week): bool
    {
        return $this->settledMonth($year, $week) !== null;
    }

    /**
     * Whether the month settled by this week contains any holiday at all.
     *
     * Used to hide the columns on a settlement week for a month that simply had
     * no bank holidays.
     */
    public function monthHasHoliday(int $year, int $week): bool
    {
        $month = $this->settledMonth($year, $week);

        if (! $month) {
            return false;
        }

        return Holiday::query()
            ->overlapping($month, $month->copy()->endOfMonth())
            ->exists();
    }

    /**
     * How a premium divides between cash and bank.
     *
     * bh_bank_percent decides this by default, but an admin can set the cash side
     * by hand for one week; passing that figure as $cashOverride makes it win.
     * Either way the two sides are the premium — the amount earned is derived
     * from the holidays actually worked and is never the admin's to change, so
     * the bank side is always whatever the cash side leaves behind.
     *
     * The override is clamped rather than trusted: attendance can be edited after
     * the split was set, and a premium that has since shrunk must not pay out a
     * cash figure larger than the whole of it.
     *
     * @return array{0: float, 1: float, 2: float} cash, bank, effective bank percent
     */
    public function splitPremium(
        float $derivedAmount,
        float $percent,
        ?float $amountOverride = null,
        ?float $bankOverride = null
    ): array {
        $amount = round(max(0.0, $amountOverride ?? $derivedAmount), 2);

        if ($amount <= 0) {
            return [0.0, 0.0, 0.0, $percent];
        }

        // The percentage applies to what the holidays actually earned, not to a
        // hand-set total. That is what makes editing the total move cash and
        // leave the bank transfer alone: paying someone extra is a decision about
        // what they are handed, not about how much of it goes to their account.
        $bank = round($bankOverride ?? ($derivedAmount * $percent / 100), 2);
        $bank = max(0.0, $bank);

        // Cash cannot go negative, so a total cut below the bank share takes the
        // difference out of bank instead. The admin is warned before saving that
        // this is happening — it changes a figure they did not type.
        $bank = min($bank, $amount);

        // Subtract rather than round a second time, so cash + bank always equals
        // the premium exactly and no cent is created or lost.
        $cash = round($amount - $bank, 2);

        // Report the split the employee actually got, not the one the percentage
        // would have produced, so payslips stay honest.
        return [$cash, $bank, $amount, round($bank / $amount * 100, 2)];
    }

    /**
     * The premium this employee has earned across the month this week settles.
     *
     * Returns zeroes for an ordinary week. Only the cash share belongs in the
     * week's earnings — the bank share is a separate transfer on top of the
     * employee's fixed weekly bank amount.
     *
     * @param  float|null  $amountOverride  a hand-set premium total, if the week has one
     * @param  float|null  $bankOverride  a hand-set bank side, if the week has one
     * @return array{0: float, 1: float, 2: float, 3: float, 4: float} amount, cash, bank, percent, derived amount
     */
    public function settlementFor(
        Employee $employee,
        int $year,
        int $week,
        ?float $amountOverride = null,
        ?float $bankOverride = null
    ): array {
        $percent = (float) ($employee->bh_bank_percent ?? 0);
        $month = $this->settledMonth($year, $week);

        if (! $month) {
            return [0.0, 0.0, 0.0, $percent, 0.0];
        }

        $total = 0.0;

        foreach ($this->holidayDatesIn($month) as $date) {
            $total += $this->premiumForDate($employee, $date);
        }

        $derived = round($total, 2);

        // A week with no holiday worked still settles an overridden total: the
        // whole point of the override is paying something the days do not imply.
        if ($derived <= 0 && ($amountOverride === null || $amountOverride <= 0)) {
            return [0.0, 0.0, 0.0, $percent, 0.0];
        }

        [$cash, $bank, $amount, $effectivePercent] = $this->splitPremium($derived, $percent, $amountOverride, $bankOverride);

        return [$amount, $cash, $bank, $effectivePercent, $derived];
    }

    /**
     * Distinct holiday dates falling inside the month.
     *
     * Deduplicated, so two overlapping holiday records covering one date can
     * never pay the premium twice. Dates are matched by calendar day rather than
     * by payroll week, so a holiday in a week that straddles two months is
     * counted against the month it actually falls in.
     *
     * @return list<Carbon>
     */
    private function holidayDatesIn(Carbon $month): array
    {
        return $this->holidayDates[$month->format('Y-m')] ??= $this->loadHolidayDatesIn($month);
    }

    /**
     * @return list<Carbon>
     */
    private function loadHolidayDatesIn(Carbon $month): array
    {
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $dates = [];

        foreach (Holiday::query()->overlapping($monthStart, $monthEnd)->get() as $holiday) {
            $cursor = Carbon::instance($holiday->start_date)->startOfDay()->max($monthStart);
            $last = Carbon::instance($holiday->end_date)->startOfDay()->min($monthEnd);

            while ($cursor->lte($last)) {
                $dates[$cursor->toDateString()] = $cursor->copy();
                $cursor->addDay();
            }
        }

        ksort($dates);

        return array_values($dates);
    }

    /**
     * What one holiday date is worth to this employee, at that date's own rate.
     */
    private function premiumForDate(Employee $employee, Carbon $date): float
    {
        if (! $employee->isPaidOn($date)) {
            return 0.0;
        }

        $weekStart = $date->copy()->startOfWeek(Carbon::MONDAY);
        $dayKey = self::DAY_KEYS[$date->dayOfWeekIso - 1];
        $isDaily = $employee->type === 'daily_rate';

        $attendance = $isDaily
            ? DailyRateAttendance::where('employee_id', $employee->id)
                ->where('year', $weekStart->isoWeekYear)
                ->where('week_number', $weekStart->isoWeek)
                ->first()
            : HourlyAttendance::where('employee_id', $employee->id)
                ->where('year', $weekStart->isoWeekYear)
                ->where('week_number', $weekStart->isoWeek)
                ->first();

        if (! $attendance) {
            return 0.0;
        }

        // Daily staff earn one day's rate; hourly staff earn every hour worked,
        // and hours_map already includes any overtime on the day.
        if ($isDaily) {
            $map = is_array($attendance->days_map) ? $attendance->days_map : [];
            $units = ! empty($map[$dayKey]) ? 1.0 : 0.0;
            $rate = (float) ($employee->rateAt($weekStart, 'daily_rate') ?? $employee->daily_rate ?? 0);
        } else {
            $map = is_array($attendance->hours_map) ? $attendance->hours_map : [];
            $units = (float) ($map[$dayKey] ?? 0);
            $rate = (float) ($employee->rateAt($weekStart, 'hourly_rate') ?? $employee->hourly_rate ?? 0);
        }

        return $units > 0 && $rate > 0 ? $units * $rate : 0.0;
    }
}
