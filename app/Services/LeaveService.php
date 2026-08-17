<?php

namespace App\Services;

use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\HourlyAttendance;
use App\Models\Leave;
use Carbon\Carbon;

/**
 * Paid leave: what an employee has earned, what they have taken, and what a
 * week of leave is worth.
 *
 * The two staff types earn leave in different currencies. Daily-rate staff get
 * four weeks of their own working week — five days a week earns twenty days,
 * six earns twenty-four — so their balance is counted in days. Hourly staff
 * have no fixed week, so they accrue a percentage of the hours they actually
 * clock, and their balance is counted in hours. Both are measured over a leave
 * year that runs from the employee's joining anniversary.
 *
 * A day of leave is only worth something if it was a working day for that
 * employee, falls before any leaving date, and was not already marked as
 * attended — a day cannot be worked and taken as leave at the same time, and
 * paying for both would pay it twice.
 */
class LeaveService
{
    /** Hourly staff accrue this percentage of every hour they clock. */
    public const HOURLY_ACCRUAL_PERCENT = 8.0;

    /** Daily-rate staff earn this many weeks of their own working week. */
    public const DAILY_ENTITLEMENT_WEEKS = 4;

    /** Weekday keys in ISO order, so the index is the offset from the week's Monday. */
    private const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * The unit an employee's leave is measured in.
     */
    public function unitFor(Employee $employee): string
    {
        return $employee->type === 'hourly' ? 'hours' : 'days';
    }

    /**
     * Leave earned over the leave year covering a given date (today by default).
     *
     * Daily-rate staff have a fixed entitlement known in advance. Hourly staff
     * accrue as they go, so their figure grows with every hour clocked and is
     * only complete once the leave year ends.
     */
    public function entitlementFor(Employee $employee, ?\DateTimeInterface $on = null): float
    {
        if ($employee->type === 'hourly') {
            $hours = $this->hoursClockedIn(
                $employee,
                $employee->leaveYearStart($on),
                $employee->leaveYearEnd($on)
            );

            return round($hours * self::HOURLY_ACCRUAL_PERCENT / 100, 2);
        }

        return (float) (self::DAILY_ENTITLEMENT_WEEKS * $employee->workingDaysPerWeek());
    }

    /**
     * Leave taken in the leave year covering a given date (today by default),
     * in days for daily-rate staff and hours for hourly staff.
     */
    public function takenFor(Employee $employee, ?\DateTimeInterface $on = null): float
    {
        $dates = $this->leaveDatesFor(
            $employee,
            $employee->leaveYearStart($on),
            $employee->leaveYearEnd($on)
        );

        if ($employee->type === 'hourly') {
            $hours = 0.0;

            foreach ($dates as $leave) {
                $hours += $this->hoursPerLeaveDay($leave, $employee);
            }

            return round($hours, 2);
        }

        return (float) count($dates);
    }

    /**
     * Everything the leave page needs to show for one employee.
     *
     * @return array{unit: string, entitlement: float, taken: float, remaining: float, year_start: Carbon, year_end: Carbon}
     */
    public function balanceFor(Employee $employee, ?\DateTimeInterface $on = null): array
    {
        $entitlement = $this->entitlementFor($employee, $on);
        $taken = $this->takenFor($employee, $on);

        return [
            'unit' => $this->unitFor($employee),
            'entitlement' => $entitlement,
            'taken' => $taken,
            'remaining' => round($entitlement - $taken, 2),
            'year_start' => $employee->leaveYearStart($on),
            'year_end' => $employee->leaveYearEnd($on),
        ];
    }

    /**
     * The leave falling in one payroll week, and what it pays.
     *
     * Leave is paid at the rate in force that week, exactly as worked time is:
     * a day's rate for daily-rate staff, the leave day's hours for hourly staff.
     * Days already marked as attended are skipped, so attendance entered by
     * mistake over a leave day costs nothing rather than paying twice.
     *
     * @return array{0: float, 1: float, 2: float} days, hours, amount
     */
    public function weekPayFor(Employee $employee, int $year, int $week): array
    {
        $weekStart = Carbon::now()->setISODate($year, $week, 1)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6);

        $dates = $this->leaveDatesFor($employee, $weekStart, $weekEnd);

        if ($dates === []) {
            return [0.0, 0.0, 0.0];
        }

        $isDaily = $employee->type === 'daily_rate';
        $attendedMap = $this->attendedMap($employee, $year, $week);

        $days = 0.0;
        $hours = 0.0;

        foreach ($dates as $dateString => $leave) {
            $dayKey = self::DAY_KEYS[Carbon::parse($dateString)->dayOfWeekIso - 1];

            if (! empty($attendedMap[$dayKey])) {
                continue;
            }

            if ($isDaily) {
                $days += 1.0;
            } else {
                $hours += $this->hoursPerLeaveDay($leave, $employee);
            }
        }

        $rate = $isDaily
            ? (float) ($employee->rateAt($weekStart, 'daily_rate') ?? $employee->daily_rate ?? 0)
            : (float) ($employee->rateAt($weekStart, 'hourly_rate') ?? $employee->hourly_rate ?? 0);

        $amount = round(($isDaily ? $days : $hours) * $rate, 2);

        return [round($days, 2), round($hours, 2), $amount];
    }

    /**
     * Hours this employee actually clocked between two dates, overtime included.
     *
     * Attendance is stored a week at a time, so whole weeks are loaded and then
     * trimmed day by day — a leave year starting mid-week must not collect the
     * hours worked before it began.
     */
    public function hoursClockedIn(Employee $employee, \DateTimeInterface $from, \DateTimeInterface $to): float
    {
        if ($employee->type !== 'hourly') {
            return 0.0;
        }

        $from = Carbon::instance($from)->startOfDay();
        $to = Carbon::instance($to)->startOfDay();

        // Widened by a year at each end: a week's stored year can differ from the
        // calendar year of its days where the two disagree at a year boundary.
        $rows = HourlyAttendance::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('year', [$from->year - 1, $to->year + 1])
            ->get();

        $total = 0.0;

        foreach ($rows as $row) {
            $monday = Carbon::now()->setISODate((int) $row->year, (int) $row->week_number, 1)->startOfDay();
            $map = is_array($row->hours_map) ? $row->hours_map : [];

            foreach (self::DAY_KEYS as $offset => $dayKey) {
                $date = $monday->copy()->addDays($offset);

                if ($date->lt($from) || $date->gt($to)) {
                    continue;
                }

                $total += (float) ($map[$dayKey] ?? 0);
            }
        }

        return round($total, 2);
    }

    /**
     * Which days of a payroll week are covered by paid leave, for each employee.
     *
     * Applies the same filtering weekPayFor() does — working days only, before
     * any leaving date — so what lights up on the attendance grid matches what
     * is actually paid rather than every date a leave record happens to span.
     *
     * @param  iterable<Employee>  $employees
     * @return array<int, array<string, Leave>> employee_id => [day key (mon..sun) => Leave]
     */
    public function weekLeaveMapFor(iterable $employees, int $year, int $week): array
    {
        $weekStart = Carbon::now()->setISODate($year, $week, 1)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6);

        $map = [];

        foreach ($employees as $employee) {
            foreach ($this->leaveDatesFor($employee, $weekStart, $weekEnd) as $dateString => $leave) {
                $dayKey = self::DAY_KEYS[Carbon::parse($dateString)->dayOfWeekIso - 1];
                $map[$employee->id][$dayKey] = $leave;
            }
        }

        return $map;
    }

    /**
     * The dates in a range that count as leave for this employee.
     *
     * Non-working days and days after a leaving date are dropped, and a date
     * covered by two overlapping records yields a single entry — which is what
     * stops one day being deducted, or paid, twice.
     *
     * @return array<string, Leave> date (Y-m-d) => the leave record covering it
     */
    public function leaveDatesFor(Employee $employee, \DateTimeInterface $from, \DateTimeInterface $to): array
    {
        $from = Carbon::instance($from)->startOfDay();
        $to = Carbon::instance($to)->startOfDay();

        $leaves = Leave::query()
            ->where('employee_id', $employee->id)
            ->overlapping($from, $to)
            ->orderBy('start_date')
            ->orderBy('id')
            ->get();

        $dates = [];

        foreach ($leaves as $leave) {
            $cursor = Carbon::instance($leave->start_date)->startOfDay();
            $last = Carbon::instance($leave->end_date)->startOfDay();

            // Clamped with copies: Carbon's max()/min() hand back one of the two
            // instances, so advancing the cursor could otherwise move the bound.
            if ($cursor->lt($from)) {
                $cursor = $from->copy();
            }
            if ($last->gt($to)) {
                $last = $to->copy();
            }

            while ($cursor->lte($last)) {
                $key = $cursor->toDateString();

                if (! isset($dates[$key]) && $employee->isWorkingDay($cursor) && $employee->isPaidOn($cursor)) {
                    $dates[$key] = $leave;
                }

                $cursor->addDay();
            }
        }

        ksort($dates);

        return $dates;
    }

    /**
     * Hours one leave day pays for an hourly employee.
     *
     * The record's own figure wins, so a short day entered by the admin stays
     * short; the employee's standard day is only the starting point.
     */
    private function hoursPerLeaveDay(Leave $leave, Employee $employee): float
    {
        return (float) ($leave->hours_per_day ?? $employee->hours_per_day ?? 0);
    }

    /**
     * The week's attendance as a day => worked map, empty when none was entered.
     *
     * @return array<string, mixed>
     */
    private function attendedMap(Employee $employee, int $year, int $week): array
    {
        if ($employee->type === 'daily_rate') {
            $attendance = DailyRateAttendance::query()
                ->where('employee_id', $employee->id)
                ->where('year', $year)
                ->where('week_number', $week)
                ->first();

            return is_array($attendance?->days_map) ? $attendance->days_map : [];
        }

        $attendance = HourlyAttendance::query()
            ->where('employee_id', $employee->id)
            ->where('year', $year)
            ->where('week_number', $week)
            ->first();

        return is_array($attendance?->hours_map) ? $attendance->hours_map : [];
    }
}
