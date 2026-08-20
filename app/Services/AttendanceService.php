<?php

namespace App\Services;

use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\HourlyAttendance;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * AttendanceService
 *
 * Eliminates the ~90% code duplication between storeDailyRate,
 * storeHourly, and storeCombined in AttendanceController.
 * Each store method in the controller now delegates here.
 */
class AttendanceService
{
    public function __construct(
        private BankHolidayService $bankHolidays,
        private LeaveService $leaves
    ) {}

    /** Weekday keys in ISO order, so the index is the offset from the week's Monday. */
    private const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /**
     * Zero out any day on or after the employee's deactivation date.
     *
     * Attendance itself still records what was marked — only the payable figures
     * derived from it are trimmed.
     *
     * @param  array<string, mixed>  $primaryMap  days_map (daily) or hours_map (hourly)
     * @param  array<string, mixed>  $extraMap  overtime_map (daily) or ot_map (hourly)
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function applyDeactivationCutoff(
        ?Employee $employee,
        Carbon $weekStart,
        array $primaryMap,
        array $extraMap
    ): array {
        if (! $employee || ! $employee->deactivated_at) {
            return [$primaryMap, $extraMap];
        }

        foreach (self::DAY_KEYS as $offset => $dayKey) {
            if ($employee->isPaidOn($weekStart->copy()->addDays($offset))) {
                continue;
            }
            $primaryMap[$dayKey] = 0;
            $extraMap[$dayKey] = 0;
        }

        return [$primaryMap, $extraMap];
    }

    /**
     * Save (upsert) attendance + payroll snapshot for a daily-rate employee.
     */
    public function saveDailyEmployee(
        int $employeeId,
        array $row,
        int $year,
        int $week,
        bool $lock
    ): void {
        $days = $row['days'] ?? [];
        $daysFull = $this->normalizeDaysMap($days);

        $presentDays = array_sum($daysFull);

        [$overtimeNormalized, $otTotal] = $this->normalizeOvertimeMap(
            $row['overtime_map'] ?? []
        );

        DailyRateAttendance::updateOrCreate(
            ['employee_id' => $employeeId, 'year' => $year, 'week_number' => $week],
            [
                'total_working_days' => 6,
                'present_days' => $presentDays,
                'days_map' => $daysFull,
                'overtime_map' => $overtimeNormalized,
                'overtime_amount' => $otTotal,
                'locked' => $lock,
            ]
        );

        $employee = Employee::find($employeeId);
        $weekStart = Carbon::now()->setISODate($year, $week, 1);
        $appliedDaily = $employee
            ? ($employee->rateAt($weekStart, 'daily_rate') ?? $employee->daily_rate)
            : 0;

        // The attendance record above keeps every marked day; pay is limited to the
        // days before any deactivation date.
        [$payableDaysMap, $payableOvertimeMap] = $this->applyDeactivationCutoff(
            $employee, $weekStart, $daysFull, $overtimeNormalized
        );
        $payableDays = array_sum($payableDaysMap);
        $payableOvertime = array_sum(array_map('floatval', $payableOvertimeMap));

        $weeklyAmount = (float) $payableDays * (float) $appliedDaily;
        $addons = $this->buildDailyAddons($payableOvertimeMap, $weekStart);
        $addonsTotal = array_sum(array_map(fn ($a) => (float) ($a['amount'] ?? 0), $addons));

        // Bank holidays are cleared once a month, in the week holding the month's
        // last day, so an ordinary week settles nothing. Only the cash share counts
        // as earnings — the bank share rides alongside as its own transfer.
        //
        // A split the admin set by hand survives this recalculation: refreshing a
        // week to pick up an attendance change must not silently undo a deliberate
        // decision about how the premium was paid. Only the amount is re-derived.
        [$bhAmount, $bhCash, $bhBank, $bhPercent] = $employee
            ? $this->bankHolidays->settlementFor($employee, $year, $week, $this->bhCashOverrideFor($employeeId, $year, $week))
            : [0.0, 0.0, 0.0, 0.0];

        // Leave is paid like the days it replaces, so it joins the week's earnings
        // rather than sitting outside them.
        [$leaveDays, $leaveHours, $leaveAmount] = $employee
            ? $this->leaves->weekPayFor($employee, $year, $week)
            : [0.0, 0.0, 0.0];

        // What the week itself earned. The bank-holiday cash share stays outside
        // this figure: it is a monthly settlement that tops up the cash handed
        // over without changing what the week's work was worth, so the weekly
        // total on the payroll page reads the same in a settlement week as in
        // any other. Gross is the two together — what cash + bank must add to.
        $weekEarnings = $weeklyAmount + $addonsTotal + $leaveAmount;
        $gross = $weekEarnings + $bhCash;
        $bankAmountFix = (float) ($employee?->bank_transfer_fix_amount ?? 0);

        // Advance balance: only advance_given is auto-computed here.
        // advance_recovered is NEVER set by AttendanceService — only saveWeek
        // (via the explicit recover input) can record a settlement.
        $prevBalance = $this->getPrevAdvanceBalance($employeeId, $year, $week);
        $advanceGiven = round(max(0.0, $bankAmountFix - $gross), 2);
        $advanceBalance = round(max(0.0, $prevBalance + $advanceGiven), 2);

        $run = $this->ensurePayrollRun($year, $week);

        PayrollItem::updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employeeId],
            [
                'payroll_run_id' => $run->id,
                'employee_id' => $employeeId,
                'type' => 'daily_rate',
                'total_days' => 6,
                'present_days' => $payableDays,
                'total_hours' => null,
                'gross_amount' => $gross,
                'cash_amount' => 0,
                'bank_amount' => $bankAmountFix,
                'weekly_amount' => $weekEarnings,
                'addons' => $addons,
                'applied_daily_rate' => $appliedDaily,
                'note' => null,
                'overtime_amount' => $payableOvertime,
                'overtime_hours' => null,
                'bh_amount' => $bhAmount,
                'bh_cash' => $bhCash,
                'bh_bank' => $bhBank,
                'applied_bh_bank_percent' => $bhPercent,
                'leave_days' => $leaveDays,
                'leave_hours' => $leaveHours,
                'leave_amount' => $leaveAmount,
                'advance_given' => $advanceGiven,
                'advance_recovered' => 0,
                'advance_balance' => $advanceBalance,
            ]
        );
    }

    /**
     * Save (upsert) attendance + payroll snapshot for an hourly employee.
     */
    public function saveHourlyEmployee(
        int $employeeId,
        array $row,
        int $year,
        int $week,
        bool $lock
    ): void {
        $employee = Employee::find($employeeId);
        $defaultHours = $employee ? (float) ($employee->hours_per_day ?? 0) : 0.0;

        [$hoursNormalized, $otNormalized, $total, $otTotal] = $this->normalizeHoursMap(
            $row['hours_map'] ?? [],
            $row['ot_map'] ?? [],
            $row['days'] ?? [],
            $defaultHours
        );

        $regularHours = max(0, $total - $otTotal);

        HourlyAttendance::updateOrCreate(
            ['employee_id' => $employeeId, 'year' => $year, 'week_number' => $week],
            [
                'hours_map' => $hoursNormalized,
                'ot_map' => $otNormalized,
                'total_hours' => $regularHours,
                'overtime_hours' => $otTotal,
                'locked' => $lock,
            ]
        );

        $weekStart = Carbon::now()->setISODate($year, $week, 1);
        $appliedHourly = $employee
            ? ($employee->rateAt($weekStart, 'hourly_rate') ?? $employee->hourly_rate)
            : 0;

        // The attendance record above keeps every logged hour; pay is limited to the
        // hours before any deactivation date.
        [$payableHoursMap, $payableOtMap] = $this->applyDeactivationCutoff(
            $employee, $weekStart, $hoursNormalized, $otNormalized
        );
        $payableOtTotal = array_sum(array_map('floatval', $payableOtMap));
        $payableRegularHours = max(0, array_sum(array_map('floatval', $payableHoursMap)) - $payableOtTotal);

        $weeklyAmount = (float) $payableRegularHours * (float) $appliedHourly;
        $addons = $this->buildHourlyAddons($payableOtMap, $appliedHourly, $weekStart);
        $addonsTotal = array_sum(array_map(fn ($a) => (float) ($a['amount'] ?? 0), $addons));

        // Same monthly settlement as daily staff; every hour worked on a holiday is
        // doubled, overtime included.
        [$bhAmount, $bhCash, $bhBank, $bhPercent] = $employee
            ? $this->bankHolidays->settlementFor($employee, $year, $week)
            : [0.0, 0.0, 0.0, 0.0];

        // Leave is paid like the hours it replaces, so it joins the week's earnings
        // rather than sitting outside them.
        [$leaveDays, $leaveHours, $leaveAmount] = $employee
            ? $this->leaves->weekPayFor($employee, $year, $week)
            : [0.0, 0.0, 0.0];

        // Same split as the daily branch: the week's own earnings, then gross on
        // top of it once the bank-holiday cash share settles.
        $weekEarnings = $weeklyAmount + $addonsTotal + $leaveAmount;
        $gross = $weekEarnings + $bhCash;
        $bankAmountFix = (float) ($employee?->bank_transfer_fix_amount ?? 0);

        // Advance balance: only advance_given is auto-computed here.
        // advance_recovered is NEVER set by AttendanceService — only saveWeek can.
        $prevBalance = $this->getPrevAdvanceBalance($employeeId, $year, $week);
        $advanceGiven = round(max(0.0, $bankAmountFix - $gross), 2);
        $advanceBalance = round(max(0.0, $prevBalance + $advanceGiven), 2);

        $run = $this->ensurePayrollRun($year, $week);

        PayrollItem::updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employeeId],
            [
                'payroll_run_id' => $run->id,
                'employee_id' => $employeeId,
                'type' => 'hourly',
                'total_days' => null,
                'present_days' => null,
                'total_hours' => $payableRegularHours,
                'gross_amount' => $gross,
                'cash_amount' => 0,
                'bank_amount' => $bankAmountFix,
                'weekly_amount' => $weekEarnings,
                'addons' => $addons,
                'applied_hourly_rate' => $appliedHourly,
                'applied_hours_per_day' => $employee?->hours_per_day ?? null,
                'overtime_hours' => $payableOtTotal,
                'overtime_amount' => null,
                'bh_amount' => $bhAmount,
                'bh_cash' => $bhCash,
                'bh_bank' => $bhBank,
                'applied_bh_bank_percent' => $bhPercent,
                'leave_days' => $leaveDays,
                'leave_hours' => $leaveHours,
                'leave_amount' => $leaveAmount,
                'advance_given' => $advanceGiven,
                'advance_recovered' => 0,
                'advance_balance' => $advanceBalance,
            ]
        );
    }

    /**
     * The hand-set cash side of this week's premium, or null if the employee's
     * percentage is still deciding it.
     */
    private function bhCashOverrideFor(int $employeeId, int $year, int $week): ?float
    {
        $override = PayrollItem::query()
            ->where('employee_id', $employeeId)
            ->whereHas('run', fn ($q) => $q
                ->where('year', $year)
                ->where('week_number', $week)
                ->where('period_type', 'weekly'))
            ->value('bh_cash_override');

        return $override === null ? null : (float) $override;
    }

    /**
     * Ensure a weekly PayrollRun row exists (draft) and return it.
     */
    public function ensurePayrollRun(int $year, int $week): PayrollRun
    {
        return PayrollRun::updateOrCreate(
            ['year' => $year, 'week_number' => $week],
            [
                'status' => 'draft',
                'created_by' => auth()->id(),
                'generated_at' => now(),
                'period_type' => 'weekly',
            ]
        );
    }

    // -------------------------------------------------------------------------
    // Normalization helpers
    // -------------------------------------------------------------------------

    /**
     * Normalize a days array to exactly the 7 day keys, all int 0|1.
     */
    public function normalizeDaysMap(array $days): array
    {
        $out = [];
        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d) {
            $out[$d] = isset($days[$d]) ? (int) $days[$d] : 0;
        }

        return $out;
    }

    /**
     * Normalize an overtime_map (daily-rate: values are amounts).
     * Returns [$normalizedMap, $totalAmount].
     */
    public function normalizeOvertimeMap(array $overtimeMap): array
    {
        $normalized = [];
        $total = 0.0;

        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d) {
            $v = isset($overtimeMap[$d]) ? (float) $overtimeMap[$d] : 0.0;
            $normalized[$d] = $v;
            $total += $v;
        }

        return [$normalized, $total];
    }

    /**
     * Normalize an hours_map (hourly: values are hours worked).
     * Returns [$hoursMap, $otMap, $totalHours, $totalOt].
     */
    public function normalizeHoursMap(
        array $hoursMap,
        array $otMap,
        array $daysFlag,
        float $defaultHours
    ): array {
        $hoursNorm = [];
        $otNorm = [];
        $total = 0.0;
        $otTotal = 0.0;

        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d) {
            $present = isset($daysFlag[$d]) ? (int) $daysFlag[$d] : null;
            $inputH = isset($hoursMap[$d]) ? (float) $hoursMap[$d] : null;
            $inputO = isset($otMap[$d]) ? (float) $otMap[$d] : 0.0;

            if ($present === null) {
                $present = ($inputH !== null && $inputH > 0) ? 1 : 0;
            }

            $h = $present ? (($inputH !== null) ? $inputH : $defaultHours) : 0.0;
            $extraOt = ($h > $defaultHours) ? ($h - $defaultHours) : 0.0;

            // Overtime is the part of the day above the norm, so it is derived from
            // the hours rather than added to whatever OT came in. Adding them made
            // the value depend on how many times the week had been saved: payroll's
            // "refresh week" feeds the stored ot_map back in, so a 10 h day on an
            // 8 h norm drifted 2 -> 4 -> 6 OT while the hours never changed. max()
            // keeps an explicitly supplied OT that exceeds the derived amount
            // (an importer may record it) without ever compounding.
            $o = max($inputO, $extraOt);

            $hoursNorm[$d] = $h;
            $otNorm[$d] = $o;
            $total += $h;
            $otTotal += $o;
        }

        return [$hoursNorm, $otNorm, $total, $otTotal];
    }

    /**
     * Build addons array from daily overtime map (amount per day → addon entry).
     */
    private function buildDailyAddons(array $overtimeNormalized, \DateTimeInterface $weekStart): array
    {
        $addons = [];
        $start = \Carbon\Carbon::instance($weekStart);

        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $i => $d) {
            $amt = $overtimeNormalized[$d] ?? 0.0;
            if ($amt > 0) {
                $addons[] = [
                    'date' => $start->copy()->addDays($i)->toDateString(),
                    'amount' => $amt,
                    'cash' => false,
                ];
            }
        }

        return $addons;
    }

    /**
     * Build addons array from hourly overtime map (hours × rate per day → addon entry).
     */
    private function buildHourlyAddons(array $otNormalized, float $rate, \DateTimeInterface $weekStart): array
    {
        $addons = [];
        $start = \Carbon\Carbon::instance($weekStart);

        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $i => $d) {
            $hrs = $otNormalized[$d] ?? 0.0;
            if ($hrs > 0) {
                $addons[] = [
                    'date' => $start->copy()->addDays($i)->toDateString(),
                    'amount' => $hrs * $rate,
                    'cash' => false,
                ];
            }
        }

        return $addons;
    }

    /**
     * Compute the running advance balance for an employee by replaying all
     * prior weeks' bank_amount and weekly_amount in chronological order.
     *
     * This derives the correct balance from data that was always stored
     * correctly (bank_amount, weekly_amount), so it works for both old records
     * (where advance_balance was 0 due to the migration default) and new ones.
     *
     * Formula per week: balance = max(0, balance + bank - earned)
     */
    /**
     * Compute the running advance balance for an employee from all prior weeks
     * that fall within the SAME calendar month as the given year/week.
     *
     * Each calendar month is a fresh cycle — balances do not carry over between months.
     * The Monday of each ISO week determines which month that week belongs to.
     *
     * Formula: balance = max(0, prev + advance_given - advance_recovered)
     */
    /**
     * Compute the running advance balance for an employee.
     *
     * Window: all weeks from the start of the last week of the previous month
     * up to (but not including) the current week. This lets an advance given
     * in the final week of a month carry forward into the next month.
     *
     * Formula: balance = max(0, prev + advance_given - advance_recovered)
     */
    private function getPrevAdvanceBalance(int $employeeId, int $year, int $week): float
    {
        $lookbackFrom = $this->advanceLookbackFrom($year, $week);

        $rows = DB::table('payroll_items as pi')
            ->join('payroll_runs as pr', 'pr.id', '=', 'pi.payroll_run_id')
            ->where('pi.employee_id', $employeeId)
            ->where(function ($q) {
                $q->where('pr.period_type', 'weekly')->orWhereNull('pr.period_type');
            })
            ->where(function ($q) use ($year, $week) {
                $q->where('pr.year', '<', $year)
                    ->orWhere(fn ($q2) => $q2->where('pr.year', $year)->where('pr.week_number', '<', $week));
            })
            ->orderBy('pr.year')
            ->orderBy('pr.week_number')
            ->select('pi.bank_amount', 'pi.gross_amount', 'pi.advance_recovered', 'pr.year', 'pr.week_number')
            ->get();

        $balance = 0.0;
        foreach ($rows as $row) {
            $itemMonday = \Carbon\Carbon::now()->setISODate($row->year, $row->week_number, 1);
            if ($itemMonday->lt($lookbackFrom)) {
                continue;
            }
            $given = max(0.0, (float) $row->bank_amount - (float) $row->gross_amount);
            $recovered = (float) ($row->advance_recovered ?? 0);
            $balance = max(0.0, $balance + $given - $recovered);
        }

        // Subtract any monthly advance settlements from BEFORE the current month.
        $currentMonthStr = \Carbon\Carbon::now()->setISODate($year, $week, 1)->format('Y-m');
        $monthlySettled = DB::table('payroll_items as pi')
            ->join('payroll_runs as pr', 'pr.id', '=', 'pi.payroll_run_id')
            ->where('pi.employee_id', $employeeId)
            ->where('pr.period_type', 'monthly')
            ->where('pr.month', '<', $currentMonthStr)
            ->sum('pi.advance_recovered');

        return max(0.0, $balance - (float) $monthlySettled);
    }

    /**
     * The earliest Monday to include in the advance-balance lookback window.
     * = Monday of the last week that started before the current month.
     * This ensures an advance from the final week of month M is visible in month M+1.
     */
    private function advanceLookbackFrom(int $year, int $week): \Carbon\Carbon
    {
        $weekMonday = \Carbon\Carbon::now()->setISODate($year, $week, 1)->startOfDay();
        // First Monday that falls on or after the 1st of the current month
        $firstMondayInMonth = $weekMonday->copy()->startOfMonth();
        while ((int) $firstMondayInMonth->dayOfWeek !== 1) {
            $firstMondayInMonth->addDay();
        }

        // One week back = Monday of the last week of the previous month
        return $firstMondayInMonth->subWeek();
    }
}
