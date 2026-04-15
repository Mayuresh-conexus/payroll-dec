<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use App\Models\PayrollRun;
use App\Models\PayrollItem;
use Carbon\Carbon;

/**
 * AttendanceService
 *
 * Eliminates the ~90% code duplication between storeDailyRate,
 * storeHourly, and storeCombined in AttendanceController.
 * Each store method in the controller now delegates here.
 */
class AttendanceService
{
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
        $days     = $row['days'] ?? [];
        $daysFull = $this->normalizeDaysMap($days);

        $presentDays = array_sum($daysFull);

        [$overtimeNormalized, $otTotal] = $this->normalizeOvertimeMap(
            $row['overtime_map'] ?? []
        );

        DailyRateAttendance::updateOrCreate(
            ['employee_id' => $employeeId, 'year' => $year, 'week_number' => $week],
            [
                'total_working_days' => 6,
                'present_days'       => $presentDays,
                'days_map'           => $daysFull,
                'overtime_map'       => $overtimeNormalized,
                'overtime_amount'    => $otTotal,
                'locked'             => $lock,
            ]
        );

        $employee  = Employee::find($employeeId);
        $weekStart = Carbon::now()->setISODate($year, $week, 1);
        $appliedDaily = $employee
            ? ($employee->rateAt($weekStart, 'daily_rate') ?? $employee->daily_rate)
            : 0;

        $weeklyAmount = (float) $presentDays * (float) $appliedDaily;
        $addons       = $this->buildDailyAddons($overtimeNormalized, $weekStart);
        $addonsTotal  = array_sum(array_map(fn ($a) => (float) ($a['amount'] ?? 0), $addons));
        $gross        = $weeklyAmount + $addonsTotal;
        $bankAmountFix = $employee?->bank_transfer_fix_amount ?? 0;

        $run = $this->ensurePayrollRun($year, $week);

        PayrollItem::updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employeeId],
            [
                'payroll_run_id'    => $run->id,
                'employee_id'       => $employeeId,
                'type'              => 'daily_rate',
                'total_days'        => 6,
                'present_days'      => $presentDays,
                'total_hours'       => null,
                'gross_amount'      => $gross,
                'cash_amount'       => 0,
                'bank_amount'       => $bankAmountFix,
                'weekly_amount'     => $weeklyAmount,
                'addons'            => $addons,
                'applied_daily_rate'=> $appliedDaily,
                'note'              => null,
                'overtime_amount'   => $otTotal,
                'overtime_hours'    => null,
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
        $employee     = Employee::find($employeeId);
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
                'hours_map'      => $hoursNormalized,
                'ot_map'         => $otNormalized,
                'total_hours'    => $regularHours,
                'overtime_hours' => $otTotal,
                'locked'         => $lock,
            ]
        );

        $weekStart    = Carbon::now()->setISODate($year, $week, 1);
        $appliedHourly = $employee
            ? ($employee->rateAt($weekStart, 'hourly_rate') ?? $employee->hourly_rate)
            : 0;

        $weeklyAmount = (float) $regularHours * (float) $appliedHourly;
        $addons       = $this->buildHourlyAddons($otNormalized, $appliedHourly, $weekStart);
        $addonsTotal  = array_sum(array_map(fn ($a) => (float) ($a['amount'] ?? 0), $addons));
        $gross        = $weeklyAmount + $addonsTotal;
        $bankAmountFix = $employee?->bank_transfer_fix_amount ?? 0;

        $run = $this->ensurePayrollRun($year, $week);

        PayrollItem::updateOrCreate(
            ['payroll_run_id' => $run->id, 'employee_id' => $employeeId],
            [
                'payroll_run_id'        => $run->id,
                'employee_id'           => $employeeId,
                'type'                  => 'hourly',
                'total_days'            => null,
                'present_days'          => null,
                'total_hours'           => $regularHours,
                'gross_amount'          => $gross,
                'cash_amount'           => 0,
                'bank_amount'           => $bankAmountFix,
                'weekly_amount'         => $weeklyAmount,
                'addons'                => $addons,
                'applied_hourly_rate'   => $appliedHourly,
                'applied_hours_per_day' => $employee?->hours_per_day ?? null,
                'overtime_hours'        => $otTotal,
                'overtime_amount'       => null,
            ]
        );
    }

    /**
     * Ensure a weekly PayrollRun row exists (draft) and return it.
     */
    public function ensurePayrollRun(int $year, int $week): PayrollRun
    {
        return PayrollRun::updateOrCreate(
            ['year' => $year, 'week_number' => $week],
            [
                'status'       => 'draft',
                'created_by'   => auth()->id(),
                'generated_at' => now(),
                'period_type'  => 'weekly',
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
        $total      = 0.0;

        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d) {
            $v              = isset($overtimeMap[$d]) ? (float) $overtimeMap[$d] : 0.0;
            $normalized[$d] = $v;
            $total         += $v;
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
        $otNorm    = [];
        $total     = 0.0;
        $otTotal   = 0.0;

        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $d) {
            $present = isset($daysFlag[$d]) ? (int) $daysFlag[$d] : null;
            $inputH  = isset($hoursMap[$d]) ? (float) $hoursMap[$d] : null;
            $inputO  = isset($otMap[$d]) ? (float) $otMap[$d] : 0.0;

            if ($present === null) {
                $present = ($inputH !== null && $inputH > 0) ? 1 : 0;
            }

            $h       = $present ? (($inputH !== null) ? $inputH : $defaultHours) : 0.0;
            $extraOt = ($h > $defaultHours) ? ($h - $defaultHours) : 0.0;
            $o       = $inputO + $extraOt;

            $hoursNorm[$d] = $h;
            $otNorm[$d]    = $o;
            $total        += $h;
            $otTotal      += $o;
        }

        return [$hoursNorm, $otNorm, $total, $otTotal];
    }

    /**
     * Build addons array from daily overtime map (amount per day → addon entry).
     */
    private function buildDailyAddons(array $overtimeNormalized, \DateTimeInterface $weekStart): array
    {
        $addons  = [];
        $start   = \Carbon\Carbon::instance($weekStart);

        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $i => $d) {
            $amt = $overtimeNormalized[$d] ?? 0.0;
            if ($amt > 0) {
                $addons[] = [
                    'date'   => $start->copy()->addDays($i)->toDateString(),
                    'amount' => $amt,
                    'cash'   => false,
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
        $start  = \Carbon\Carbon::instance($weekStart);

        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $i => $d) {
            $hrs = $otNormalized[$d] ?? 0.0;
            if ($hrs > 0) {
                $addons[] = [
                    'date'   => $start->copy()->addDays($i)->toDateString(),
                    'amount' => $hrs * $rate,
                    'cash'   => false,
                ];
            }
        }

        return $addons;
    }
}
