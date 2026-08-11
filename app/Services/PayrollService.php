<?php

namespace App\Services;

use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\HourlyAttendance;
use App\Models\PayrollRun;
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
    /** Weekday keys in ISO order, so the index is the offset from the week's Monday. */
    private const DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

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

            $totalDays = $att->total_working_days ?? 6;
            $presentDays = $att->present_days ?? 0;
            $dailyRate = $employee->rateAt($weekStart, 'daily_rate') ?? $employee->daily_rate ?? 0;
            $overtimeAmount = $att->overtime_amount ?? 0;

            // Deactivated mid-week: only days before the deactivation date are payable.
            if ($employee->deactivated_at) {
                $daysMap = is_array($att->days_map) ? $att->days_map : [];
                $overtimeMap = is_array($att->overtime_map) ? $att->overtime_map : [];
                $presentDays = 0;
                $overtimeAmount = 0.0;

                foreach (self::DAY_KEYS as $offset => $dayKey) {
                    if (! $employee->isPaidOn($weekStart->copy()->addDays($offset))) {
                        continue;
                    }
                    if (! empty($daysMap[$dayKey])) {
                        $presentDays++;
                    }
                    $overtimeAmount += (float) ($overtimeMap[$dayKey] ?? 0);
                }
            }
            $bankAmountFix = $employee->bank_transfer_fix_amount ?? 0;
            $gross = ($presentDays * $dailyRate) + $overtimeAmount;
            $sunPresent = ! empty($att->days_map['sun']) && (int) $att->days_map['sun'] === 1;

            $rows->push([
                'employee' => $employee,
                'type' => 'daily_rate',
                'rate' => (float) $dailyRate,
                'total_days' => $totalDays,
                'present_days' => $presentDays,
                'sun_present' => $sunPresent,
                'total_hours' => null,
                'sun_hours' => null,
                'overtime_amount' => $overtimeAmount,
                'gross_amount' => $gross,
                'cash_amount' => 0,
                'bank_amount' => $bankAmountFix,
                'bank_transfer_fix_amount' => (float) $bankAmountFix,
            ]);
        }

        foreach ($hourlyAtt as $att) {
            $employee = $att->employee;
            if (! $employee) {
                continue;
            }

            $hours = $att->total_hours ?? 0;
            $ot = $att->overtime_hours ?? 0;

            // Deactivated mid-week: only hours before the deactivation date are payable.
            // hours_map holds each day's total (OT included); ot_map the OT portion.
            if ($employee->deactivated_at) {
                $hoursMap = is_array($att->hours_map) ? $att->hours_map : [];
                $otMap = is_array($att->ot_map) ? $att->ot_map : [];
                $hours = 0.0;
                $ot = 0.0;

                foreach (self::DAY_KEYS as $offset => $dayKey) {
                    if (! $employee->isPaidOn($weekStart->copy()->addDays($offset))) {
                        continue;
                    }
                    $dayTotal = (float) ($hoursMap[$dayKey] ?? 0);
                    $dayOt = (float) ($otMap[$dayKey] ?? 0);
                    $hours += max(0, $dayTotal - $dayOt);
                    $ot += $dayOt;
                }
            }

            $rate = $employee->rateAt($weekStart, 'hourly_rate') ?? $employee->hourly_rate ?? 0;
            $gross = ($hours * $rate) + ($ot * $rate);
            $bankAmountFix = $employee->bank_transfer_fix_amount ?? 0;
            $sunHours = ! empty($att->hours_map['sun']) ? (float) $att->hours_map['sun'] : 0;

            $rows->push([
                'employee' => $employee,
                'type' => 'hourly',
                'rate' => (float) $rate,
                'total_days' => null,
                'present_days' => null,
                'sun_present' => $sunHours > 0,
                'sun_hours' => $sunHours,
                'total_hours' => $hours,
                'overtime_hours' => $ot,
                'gross_amount' => $gross,
                'cash_amount' => 0,
                'bank_amount' => $bankAmountFix,
                'bank_transfer_fix_amount' => (float) $bankAmountFix,
            ]);
        }

        // Batch-load each employee's most recent advance_balance from prior weeks
        $empIds = $rows->pluck('employee.id')->filter()->unique()->values()->all();
        $prevBalances = $this->loadPrevAdvanceBalances($empIds, $year, $week);

        return $rows->map(function (array $row) use ($prevBalances) {
            $id = $row['employee']?->id;
            $row['prev_advance_balance'] = $id ? (float) ($prevBalances[$id] ?? 0) : 0.0;

            return $row;
        });
    }

    /**
     * For each employee ID, compute the running advance balance from all weeks
     * strictly before the given year/week that fall within the SAME calendar month.
     *
     * Each calendar month is a fresh cycle — balances do not carry over between months.
     * The Monday of each ISO week determines which month that week belongs to.
     *
     * @param  int[]  $empIds
     */
    private function loadPrevAdvanceBalances(array $empIds, int $year, int $week): array
    {
        if (empty($empIds)) {
            return [];
        }

        $allRows = DB::table('payroll_items as pi')
            ->join('payroll_runs as pr', 'pr.id', '=', 'pi.payroll_run_id')
            ->whereIn('pi.employee_id', $empIds)
            ->where(function ($q) {
                $q->where('pr.period_type', 'weekly')->orWhereNull('pr.period_type');
            })
            ->where(function ($q) use ($year, $week) {
                $q->where('pr.year', '<', $year)
                    ->orWhere(fn ($q2) => $q2->where('pr.year', $year)->where('pr.week_number', '<', $week));
            })
            ->orderBy('pr.year')
            ->orderBy('pr.week_number')
            ->select('pi.employee_id', 'pi.bank_amount', 'pi.weekly_amount', 'pi.advance_recovered', 'pr.year', 'pr.week_number')
            ->get()
            ->groupBy('employee_id');

        $result = [];
        foreach ($allRows as $empId => $items) {
            $balance = 0.0;
            foreach ($items as $item) {
                $given = max(0.0, (float) $item->bank_amount - (float) $item->weekly_amount);
                $recovered = (float) ($item->advance_recovered ?? 0);
                $balance = max(0.0, $balance + $given - $recovered);
            }
            $result[$empId] = $balance;
        }

        // Subtract any monthly advance settlements from BEFORE the current month.
        // If admin settled the advance in a prior month's monthly payroll, it clears the carry-over.
        if (! empty($result)) {
            $currentMonthStr = Carbon::create()->setISODate($year, $week, 1)->format('Y-m');

            $monthlySettlements = DB::table('payroll_items as pi')
                ->join('payroll_runs as pr', 'pr.id', '=', 'pi.payroll_run_id')
                ->whereIn('pi.employee_id', array_keys($result))
                ->where('pr.period_type', 'monthly')
                ->where('pr.month', '<', $currentMonthStr)
                ->select('pi.employee_id', DB::raw('SUM(pi.advance_recovered) as total'))
                ->groupBy('pi.employee_id')
                ->pluck('total', 'employee_id')
                ->all();

            foreach ($result as $empId => &$balance) {
                $balance = max(0.0, $balance - (float) ($monthlySettlements[$empId] ?? 0));
            }
            unset($balance);
        }

        return $result;
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

            return ($emp ? $emp->id : 'emp0').'|'.$row['type'];
        });

        if ($run) {
            foreach ($run->items as $item) {
                $employee = $item->employee;
                if (! $employee) {
                    continue;
                }

                $key = $employee->id.'|'.$item->type;
                $isDaily = $item->type === 'daily_rate';
                $isHourly = $item->type === 'hourly';
                $trusted = ($isDaily && $dailyLocked) || ($isHourly && $hourlyLocked);
                $addons = $this->decodeAddons($item->addons);

                // prev_advance_balance: use what buildRowsFromAttendance already looked up,
                // or fall back to deriving it from the saved item's stored fields
                $prevAdvanceBalance = (float) ($rowsByKey[$key]['prev_advance_balance']
                    ?? ($item->advance_balance - $item->advance_given + $item->advance_recovered)
                    ?? 0);

                $empBankFix = (float) ($employee->bank_transfer_fix_amount ?? 0);

                if ($trusted) {
                    $gross = max(0, (float) ($item->gross_amount ?? 0));
                    $itemCash = (float) ($item->cash_amount ?? 0);
                    $itemBank = (float) ($item->bank_amount ?? 0);
                    $isSaved = $itemCash > 0 || abs($itemBank - $empBankFix) > 0.005;
                    $weeklyAmount = (float) ($item->weekly_amount ?? $gross);

                    if ($isSaved) {
                        $cash = $this->clampCash($itemCash, $gross);
                        $bankAmount = ($cash > 0) ? ($gross - $cash) : min($empBankFix, $gross);
                    } else {
                        // Attendance was saved/recalculated but payroll was never explicitly
                        // saved via "Save Weekly Payroll" — mirror the same preview split the
                        // web page computes client-side, so exports/reports match the screen.
                        $cash = max(0, $weeklyAmount - $empBankFix);
                        $bankAmount = min($empBankFix, $gross);
                    }

                    [$recover, $arrears] = $this->computeAdvance($prevAdvanceBalance, $weeklyAmount, $bankAmount, $cash, $isSaved);

                    $rate = (float) ($isDaily
                        ? ($item->applied_daily_rate ?? $rowsByKey[$key]['rate'] ?? $employee->daily_rate ?? 0)
                        : ($item->applied_hourly_rate ?? $rowsByKey[$key]['rate'] ?? $employee->hourly_rate ?? 0));

                    $rowsByKey[$key] = [
                        'employee' => $employee,
                        'type' => $item->type,
                        'rate' => $rate,
                        'total_days' => $item->total_days,
                        'present_days' => $item->present_days,
                        'total_hours' => $item->total_hours,
                        ($isDaily ? 'overtime_amount' : 'overtime_hours') => $isDaily ? $item->overtime_amount : $item->overtime_hours,
                        'sun_hours' => $rowsByKey[$key]['sun_hours'] ?? 0,
                        'gross_amount' => $gross,
                        'cash_amount' => $cash,
                        'bank_amount' => $bankAmount,
                        'bank_transfer_fix_amount' => $empBankFix,
                        'weekly_amount' => $weeklyAmount,
                        'addons' => $addons,
                        'prev_advance_balance' => $prevAdvanceBalance,
                        'advance_balance' => (float) ($item->advance_balance ?? 0),
                        'recover' => $recover,
                        'arrears' => $arrears,
                    ];
                } elseif ($rowsByKey->has($key)) {
                    $row = $rowsByKey->get($key);
                    $gross = (float) ($row['gross_amount'] ?? 0);
                    $itemCash = (float) ($item->cash_amount ?? 0);
                    $itemBank = (float) ($item->bank_amount ?? 0);
                    $isSaved = $itemCash > 0 || abs($itemBank - $empBankFix) > 0.005;
                    $weeklyAmount = (float) ($item->weekly_amount ?? $gross);

                    if ($isSaved) {
                        $cash = $this->clampCash($itemCash, $gross);
                        $bankAmount = $itemBank;
                    } else {
                        $cash = max(0, $weeklyAmount - $empBankFix);
                        $bankAmount = $empBankFix;
                    }

                    [$recover, $arrears] = $this->computeAdvance($prevAdvanceBalance, $weeklyAmount, $bankAmount, $cash, $isSaved);

                    $row['cash_amount'] = $cash;
                    $row['bank_amount'] = ($cash > 0) ? $bankAmount : $empBankFix;
                    $row['bank_transfer_fix_amount'] = $empBankFix;
                    $row['weekly_amount'] = $weeklyAmount;
                    $row['addons'] = $addons;
                    $row['prev_advance_balance'] = $prevAdvanceBalance;
                    $row['advance_balance'] = (float) ($item->advance_balance ?? 0);
                    $row['recover'] = $recover;
                    $row['arrears'] = $arrears;

                    $rowsByKey[$key] = $row;
                } else {
                    // Payroll row exists but attendance row is missing
                    $gross = max(0, (float) ($item->gross_amount ?? 0));
                    $itemCash = (float) ($item->cash_amount ?? 0);
                    $itemBank = (float) ($item->bank_amount ?? 0);
                    $isSaved = $itemCash > 0 || abs($itemBank - $empBankFix) > 0.005;
                    $weeklyAmount = (float) ($item->weekly_amount ?? $gross);

                    if ($isSaved) {
                        $cash = $this->clampCash($itemCash, $gross);
                        $bankAmount = $gross - $cash;
                    } else {
                        $cash = max(0, $weeklyAmount - $empBankFix);
                        $bankAmount = $empBankFix;
                    }

                    [$recover, $arrears] = $this->computeAdvance($prevAdvanceBalance, $weeklyAmount, $bankAmount, $cash, $isSaved);

                    $rate = (float) ($isDaily
                        ? ($item->applied_daily_rate ?? $employee->daily_rate ?? 0)
                        : ($item->applied_hourly_rate ?? $employee->hourly_rate ?? 0));

                    $rowsByKey[$key] = [
                        'employee' => $employee,
                        'type' => $item->type,
                        'rate' => $rate,
                        'total_days' => $item->total_days,
                        'present_days' => $item->present_days,
                        'total_hours' => $item->total_hours,
                        ($isDaily ? 'overtime_amount' : 'overtime_hours') => $item->overtime_hours,
                        'sun_hours' => 0,
                        'gross_amount' => $gross,
                        'cash_amount' => $cash,
                        'bank_amount' => $bankAmount,
                        'bank_transfer_fix_amount' => $empBankFix,
                        'weekly_amount' => $weeklyAmount,
                        'addons' => $addons,
                        'prev_advance_balance' => $prevAdvanceBalance,
                        'advance_balance' => (float) ($item->advance_balance ?? 0),
                        'recover' => $recover,
                        'arrears' => $arrears,
                    ];
                }
            }
        } else {
            // No payroll run yet — attendance-only rows always start unsaved
            $rowsByKey = $rowsByKey->map(function (array $row) {
                $gross = (float) ($row['gross_amount'] ?? 0);
                $bankFix = (float) ($row['bank_amount'] ?? 0); // pre-set to bank_transfer_fix_amount
                $weeklyAmount = (float) ($row['weekly_amount'] ?? $gross);
                $cash = max(0, $weeklyAmount - $bankFix);
                $bankAmount = min($bankFix, $gross);

                $row['cash_amount'] = $cash;
                $row['bank_amount'] = $bankAmount;
                $row['bank_transfer_fix_amount'] ??= $bankFix;
                $row['weekly_amount'] = $weeklyAmount;
                $row['addons'] ??= [];
                $row['prev_advance_balance'] ??= 0.0;
                $row['advance_balance'] ??= 0.0;

                [$recover, $arrears] = $this->computeAdvance(
                    (float) $row['prev_advance_balance'],
                    $weeklyAmount,
                    $bankAmount,
                    $cash,
                    false
                );
                $row['recover'] = $recover;
                $row['arrears'] = $arrears;

                return $row;
            });
        }

        // Attendance tables have no natural row order (daily-rate rows are queried
        // before hourly ones, and within each group rows come back in whatever order
        // the DB happens to return them — not employee_code). Sort explicitly so the
        // payroll table always lists employees in a stable, predictable order.
        return $rowsByKey->values()->sortBy(
            fn (array $row) => $row['employee']?->employee_code ?? '',
            SORT_NATURAL | SORT_FLAG_CASE
        )->values();
    }

    /**
     * Replicates the web page's client-side (payroll.js) advance/arrears preview
     * so exports and other server-side consumers show the same figures as the screen,
     * even for a week whose payroll amounts were never explicitly saved.
     *
     * @return array{0: float, 1: float} [$recover, $arrears]
     */
    private function computeAdvance(float $prevBalance, float $weeklyAmount, float $bankAmount, float $cashAmount, bool $isSaved): array
    {
        $recover = 0.0;
        if ($isSaved) {
            $normalCash = max(0, $weeklyAmount - $bankAmount);
            $recover = max(0, round(($normalCash - $cashAmount) * 100) / 100);
        }

        $given = max(0, $bankAmount - $weeklyAmount);
        $arrears = max(0, round(($prevBalance + $given - $recover) * 100) / 100);

        return [$recover, $arrears];
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
