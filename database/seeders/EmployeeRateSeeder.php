<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\EmployeeRate;
use Carbon\Carbon;

class EmployeeRateSeeder extends Seeder
{
    public function run(): void
    {
        $employees = Employee::all();

        foreach ($employees as $employee) {
            $joiningDate = $employee->joining_date ?? Carbon::now()->subYear();

            if ($employee->type === 'daily_rate' && $employee->daily_rate) {
                // Initial rate at joining
                $initialRate = round($employee->daily_rate * 0.85, 0); // started 15% lower
                EmployeeRate::create([
                    'employee_id' => $employee->id,
                    'rate_type' => 'daily_rate',
                    'amount' => $initialRate,
                    'effective_from' => $joiningDate->toDateString(),
                    'effective_to' => Carbon::now()->subMonths(3)->toDateString(),
                    'created_by' => 1,
                ]);

                // Current rate (recent revision)
                EmployeeRate::create([
                    'employee_id' => $employee->id,
                    'rate_type' => 'daily_rate',
                    'amount' => $employee->daily_rate,
                    'effective_from' => Carbon::now()->subMonths(3)->addDay()->toDateString(),
                    'effective_to' => null,
                    'created_by' => 1,
                ]);

                // hours_per_day rate entry
                EmployeeRate::create([
                    'employee_id' => $employee->id,
                    'rate_type' => 'hours_per_day',
                    'amount' => $employee->hours_per_day ?? 8,
                    'effective_from' => $joiningDate->toDateString(),
                    'effective_to' => null,
                    'created_by' => 1,
                ]);
            }

            if ($employee->type === 'hourly' && $employee->hourly_rate) {
                // Initial hourly rate at joining
                $initialRate = round($employee->hourly_rate * 0.80, 0); // started 20% lower
                EmployeeRate::create([
                    'employee_id' => $employee->id,
                    'rate_type' => 'hourly_rate',
                    'amount' => $initialRate,
                    'effective_from' => $joiningDate->toDateString(),
                    'effective_to' => Carbon::now()->subMonths(2)->toDateString(),
                    'created_by' => 1,
                ]);

                // Intermediate rate bump
                $midRate = round($employee->hourly_rate * 0.90, 0);
                EmployeeRate::create([
                    'employee_id' => $employee->id,
                    'rate_type' => 'hourly_rate',
                    'amount' => $midRate,
                    'effective_from' => Carbon::now()->subMonths(2)->addDay()->toDateString(),
                    'effective_to' => Carbon::now()->subMonth()->toDateString(),
                    'created_by' => 1,
                ]);

                // Current rate
                EmployeeRate::create([
                    'employee_id' => $employee->id,
                    'rate_type' => 'hourly_rate',
                    'amount' => $employee->hourly_rate,
                    'effective_from' => Carbon::now()->subMonth()->addDay()->toDateString(),
                    'effective_to' => null,
                    'created_by' => 1,
                ]);

                // hours_per_day rate entry
                EmployeeRate::create([
                    'employee_id' => $employee->id,
                    'rate_type' => 'hours_per_day',
                    'amount' => $employee->hours_per_day ?? 8,
                    'effective_from' => $joiningDate->toDateString(),
                    'effective_to' => null,
                    'created_by' => 1,
                ]);
            }
        }
    }
}
