<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use App\Models\PayrollRun;
use App\Models\PayrollItem;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * PayrollService
 *
 * Contains all payroll calculation and data-merging logic,
 * extracted from PayrollController to keep controllers thin
 * and make the logic independently testable.
 */
class PayrollService
{
    /**
     * Build fresh attendance-based rows for a given year/week.
     * Each row is an array describing one employee's pay for the week.
     */
    public function buildRowsFromAttendance(int $year, int $week): Collection
    {
        $dailyAtt = DailyRateAttendance::with('employee')
            ->where('year', $year)
            ->where('week_number', $week)
            ->get();

        $hourlyAtt = HourlyAttendance::with('employee')
            ->where('year', $year)
            ->where('week_number', $week)
            ->get();

        $rows = collect();
        $weekStart = Carbon::now()->setISODate($year, $week, 1);

        foreach ($dailyAtt as $att) {
            $employee = $att->employee;
            if (! $employee) {
                continue;
            }

            $totalDays      = $att->total_working_days ?? 6;
            $presentDays    = $att->present_days ?? 0;
            $dailyRate      = $employee->rateAt($weekStart, 'daily_rate') ?? $employee->daily_rate ?? 0;
            $overtimeAmount = $att->overtime_amount ?? 0;
            $bankAmountFix  = $employee->bank_transfer_fix_amount ?? 0;
            $gross          = ($presentDays * $dailyRate) + $overtimeAmount;
            $sunPresent     = ! empty($att->days_map['sun']) && (int) $att->days_map['sun'] === 1;

            $rows->push([
                'employee'        => $employee,
                'type'            => 'daily_rate',
                'total_days'      => $totalDays,
                'present_days'    => $presentDays,
                'sun_present'     => $sunPresent,
                'total_hours'     => null,
                'sun_hours'       => null,
                'overtime_amount' => $overtimeAmount,
                'gross_amount'    => $gross,
                'cash_amount'     => 0,
                'bank_amount'     => $bankAmountFix,
            ]);
        }

        foreach ($hourlyAtt as $att) {
            $employee = $att->employee;
            if (! $employee) {
                continue;
            }

            $hours         = $att->total_hours ?? 0;
            $ot            = $att->overtime_hours ?? 0;
            $rate          = $employee->rateAt($weekStart, 'hourly_rate') ?? $employee->hourly_rate ?? 0;
            $gross         = ($hours * $rate) + ($ot * $rate);
            $bankAmountFix = $employee->bank_transfer_fix_amount ?? 0;
            $sunHours      = ! empty($att->hours_map['sun']) ? (float) $att->hours_map['sun'] : 0;

            $rows->push([
                'employee'       => $employee,
                'type'           => 'hourly',
                'total_days'     => null,
                'present_days'   => null,
                'sun_present'    => $sunHours > 0,
                'sun_hours'      => $sunHours,
                'total_hours'    => $hours,
                'overtime_hours' => $ot,
                'gross_amount'   => $gross,
                'cash_amount'    => 0,
                'bank_amount'    => $bankAmountFix,
            ]);
        }

        // Batch-load each employee's most recent advance_balance from prior weeks
        $empIds      = $rows->pluck('employee.id')->filter()->unique()->values()->all();
        $prevBalances= $this->loadPrevAdvanceBalances($empIds, $year, $week);

        return $rows->map(function (array $row) use ($prevBalances) {
            $id = $row['employee']?->id;
            $row['prev_advance_balance'] = $id ? (float) ($prevBalances[$id] ?? 0) : 0.0;
            return $row;
        });
    }

    /**
     * For each employee ID, fetch the advance_balance from their most recent
     * PayrollItem in a week strictly before the given year/week.
     *
     * @param  int[]  $empIds
     */
    private function loadPrevAdvanceBalances(array $empIds, int $year, int $week): array
    {
        if (empty($empIds)) {
            return [];
        }

        $rows = DB::table('payroll_items as pi')
            ->join('payroll_runs as pr', 'pr.id', '=', 'pi.payroll_run_id')
            ->whereIn('pi.employee_id', $empIds)
            ->where('pr.period_type', 'weekly')
            ->where(function ($q) use ($year, $week) {
                $q->where('pr.year', '<', $year)
                  ->orWhere(fn ($q2) => $q2->where('pr.year', $year)->where('pr.week_number', '<', $week));
            })
            ->select('pi.employee_id', 'pi.advance_balance', 'pr.year', 'pr.week_number')
            ->get()
            ->groupBy('employee_id')
            ->map(function ($items) {
                // Pick the entry for the most recent week
                return $items
                    ->sortByDesc(fn ($r) => $r->year * 100 + $r->week_number)
                    ->first()
                    ->advance_balance ?? 0;
            })
            ->all();

        return array_map('floatval', $rows);
    }

    /**
     * Merge attendance-fresh rows with any existing PayrollRun data.
     * Returns the final Collection of rows ready for the payroll view.
     */
    public function mergeWithPayrollRun(
        Collection $rows,
        ?PayrollRun $run,
        bool $dailyLocked,
        bool $hourlyLocked
    ): Collection {
        $rowsByKey = $rows->keyBy(function (array $row) {
            $emp = $row['employee'];
            return ($emp ? $emp->id : 'emp0') . '|' . $row['type'];
        });

        if ($run) {
            foreach ($run->items as $item) {
                $employee = $item->employee;
                if (! $employee) {
                    continue;
                }

                $key      = $employee->id . '|' . $item->type;
                $isDaily  = $item->type === 'daily_rate';
                $isHourly = $item->type === 'hourly';
                $trusted  = ($isDaily && $dailyLocked) || ($isHourly && $hourlyLocked);
                $addons   = $this->decodeAddons($item->addons);

                // prev_advance_balance: use what buildRowsFromAttendance already looked up,
                // or fall back to deriving it from the saved item's stored fields
                $prevAdvanceBalance = (float) ($rowsByKey[$key]['prev_advance_balance']
                    ?? ($item->advance_balance - $item->advance_given + $item->advance_recovered)
                    ?? 0);

                if ($trusted) {
                    $gross       = max(0, (float) ($item->gross_amount ?? 0));
                    $cash        = $this->clampCash((float) ($item->cash_amount ?? 0), $gross);
                    $bankFix     = $employee->bank_transfer_fix_amount ?? 0;
                    $bankAmount  = ($cash > 0) ? ($gross - $cash) : min((float) $bankFix, $gross);

                    $rowsByKey[$key] = [
                        'employee'             => $employee,
                        'type'                 => $item->type,
                        'total_days'           => $item->total_days,
                        'present_days'         => $item->present_days,
                        'total_hours'          => $item->total_hours,
                        ($isDaily ? 'overtime_amount' : 'overtime_hours') => $isDaily ? $item->overtime_amount : $item->overtime_hours,
                        'sun_hours'            => $rowsByKey[$key]['sun_hours'] ?? 0,
                        'gross_amount'         => $gross,
                        'cash_amount'          => $cash,
                        'bank_amount'          => $bankAmount,
                        'weekly_amount'        => $item->weekly_amount ?? $gross,
                        'addons'               => $addons,
                        'prev_advance_balance' => $prevAdvanceBalance,
                        'advance_balance'      => (float) ($item->advance_balance ?? 0),
                    ];
                } elseif ($rowsByKey->has($key)) {
                    $row           = $rowsByKey->get($key);
                    $gross         = (float) ($row['gross_amount'] ?? 0);
                    $cash          = $this->clampCash((float) ($item->cash_amount ?? 0), $gross);
                    $bankAmountFix = $employee->bank_transfer_fix_amount ?? 0;
                    $bankAmount    = (float) ($item->bank_amount ?? 0);

                    $row['cash_amount']          = $cash;
                    $row['bank_amount']          = ($cash > 0) ? $bankAmount : $bankAmountFix;
                    $row['weekly_amount']        = $item->weekly_amount ?? ($row['gross_amount'] ?? 0);
                    $row['addons']               = $addons;
                    $row['prev_advance_balance'] = $prevAdvanceBalance;
                    $row['advance_balance']      = (float) ($item->advance_balance ?? 0);

                    $rowsByKey[$key] = $row;
                } else {
                    // Payroll row exists but attendance row is missing
                    $gross = max(0, (float) ($item->gross_amount ?? 0));
                    $cash  = $this->clampCash((float) ($item->cash_amount ?? 0), $gross);

                    $rowsByKey[$key] = [
                        'employee'             => $employee,
                        'type'                 => $item->type,
                        'total_days'           => $item->total_days,
                        'present_days'         => $item->present_days,
                        'total_hours'          => $item->total_hours,
                        ($isDaily ? 'overtime_amount' : 'overtime_hours') => $item->overtime_hours,
                        'sun_hours'            => 0,
                        'gross_amount'         => $gross,
                        'cash_amount'          => $cash,
                        'bank_amount'          => $gross - $cash,
                        'weekly_amount'        => $item->weekly_amount ?? $gross,
                        'addons'               => $addons,
                        'prev_advance_balance' => $prevAdvanceBalance,
                        'advance_balance'      => (float) ($item->advance_balance ?? 0),
                    ];
                }
            }
        } else {
            // No payroll run yet — ensure defaults
            $rowsByKey = $rowsByKey->map(function (array $row) {
                $gross   = (float) ($row['gross_amount'] ?? 0);
                $cash    = $this->clampCash((float) ($row['cash_amount'] ?? 0), $gross);
                $bankFix = (float) ($row['bank_amount'] ?? 0); // pre-set to bank_transfer_fix_amount

                $row['cash_amount']          = $cash;
                $row['bank_amount']          = ($cash > 0) ? ($gross - $cash) : min($bankFix, $gross);
                $row['weekly_amount']        ??= $gross;
                $row['addons']               ??= [];
                $row['prev_advance_balance'] ??= 0.0;
                $row['advance_balance']      ??= 0.0;

                return $row;
            });
        }

        return $rowsByKey->values();
    }

    /**
     * Ensure cash is within [0, gross].
     */
    public function clampCash(float $cash, float $gross): float
    {
        return max(0, min($cash, $gross));
    }

    /**
     * Decode addons from JSON string or return as-is if already array.
     */
    public function decodeAddons(mixed $addons): array
    {
        if (is_string($addons)) {
            return json_decode($addons, true) ?: [];
        }

        return is_array($addons) ? $addons : [];
    }
}
