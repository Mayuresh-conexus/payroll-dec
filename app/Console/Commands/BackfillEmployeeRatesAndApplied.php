<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Employee;
use App\Models\EmployeeRate;
use App\Models\PayrollRun;
use App\Models\PayrollItem;
use Carbon\Carbon;
use DB;

class BackfillEmployeeRatesAndApplied extends Command
{
    protected $signature = 'payroll:backfill-rates-applied';
    protected $description = 'Backfill employee_rates from employees and populate payroll_items.applied_* for existing payrolls';

    public function handle()
    {
        $this->info('Starting backfill...');

        // 1) backfill employee_rates when none exist for employee
        $employees = Employee::all();
        $created = 0;

        foreach ($employees as $emp) {
            $joining = $emp->joining_date ? $emp->joining_date->toDateString() : Carbon::now()->subYears(20)->toDateString();

            // daily_rate
            if (! $emp->rates()->where('rate_type', 'daily_rate')->exists() && $emp->daily_rate !== null) {
                EmployeeRate::create([
                    'employee_id' => $emp->id,
                    'rate_type' => 'daily_rate',
                    'amount' => $emp->daily_rate,
                    'effective_from' => $joining,
                    'created_by' => null,
                ]);
                $created++;
            }

            // hourly_rate
            if (! $emp->rates()->where('rate_type', 'hourly_rate')->exists() && $emp->hourly_rate !== null) {
                EmployeeRate::create([
                    'employee_id' => $emp->id,
                    'rate_type' => 'hourly_rate',
                    'amount' => $emp->hourly_rate,
                    'effective_from' => $joining,
                    'created_by' => null,
                ]);
                $created++;
            }

            // hours_per_day
            if (! $emp->rates()->where('rate_type', 'hours_per_day')->exists() && $emp->hours_per_day !== null) {
                EmployeeRate::create([
                    'employee_id' => $emp->id,
                    'rate_type' => 'hours_per_day',
                    'amount' => $emp->hours_per_day,
                    'effective_from' => $joining,
                    'created_by' => null,
                ]);
                $created++;
            }
        }

        $this->info("EmployeeRates created: {$created}");

        // 2) backfill payroll_items.applied_* for items missing these values
        $items = PayrollItem::with('run','employee')->whereNull('applied_daily_rate')->orWhereNull('applied_hourly_rate')->get();
        $updated = 0;

        foreach ($items as $item) {
            $run = $item->payrollRun ?? $item->run;
            if (! $run) continue;

            $year = (int) ($run->year ?? 0);
            $week = (int) ($run->week_number ?? 0);
            if ($year <= 0 || $week <= 0) continue;

            $weekStart = Carbon::now()->setISODate($year, $week, 1);
            $emp = $item->employee;
            if (! $emp) continue;

            $appliedDaily = $emp->rateAt($weekStart, 'daily_rate') ?? $emp->daily_rate;
            $appliedHourly = $emp->rateAt($weekStart, 'hourly_rate') ?? $emp->hourly_rate;
            $appliedHoursPerDay = $emp->rateAt($weekStart, 'hours_per_day') ?? $emp->hours_per_day;

            $item->applied_daily_rate = $appliedDaily;
            $item->applied_hourly_rate = $appliedHourly;
            $item->applied_hours_per_day = $appliedHoursPerDay;
            $item->save();
            $updated++;
        }

        $this->info("PayrollItem rows updated: {$updated}");

        $this->info('Backfill complete.');
        return 0;
    }
}
