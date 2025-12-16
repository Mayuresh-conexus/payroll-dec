<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use Carbon\Carbon;

class EmployeeSeeder extends Seeder
{
    public function run(): void
    {
        $employees = [
            [
                'employee_code' => 'EMP001',
                'name' => 'John Doe',
                'joining_date' => Carbon::now()->subYears(2),
                'department' => 'Production',
                'type' => 'daily_rate',
                'daily_rate' => 1200,
                'hourly_rate' => null,
                'hours_per_day' => 8,
            ],
            [
                'employee_code' => 'EMP002',
                'name' => 'Jane Smith',
                'joining_date' => Carbon::now()->subYear(),
                'department' => 'Quality',
                'type' => 'hourly',
                'daily_rate' => null,
                'hourly_rate' => 180,
                'hours_per_day' => 8,
            ],
            [
                'employee_code' => 'EMP003',
                'name' => 'Michael Brown',
                'joining_date' => Carbon::now()->subMonths(8),
                'department' => 'Maintenance',
                'type' => 'daily_rate',
                'daily_rate' => 1000,
                'hourly_rate' => null,
                'hours_per_day' => 8,
            ],
            [
                'employee_code' => 'EMP004',
                'name' => 'Sara Wilson',
                'joining_date' => Carbon::now()->subMonths(5),
                'department' => 'Admin',
                'type' => 'hourly',
                'daily_rate' => null,
                'hourly_rate' => 200,
                'hours_per_day' => 7.5,
            ],
            [
                'employee_code' => 'EMP005',
                'name' => 'David Kumar',
                'joining_date' => Carbon::now()->subYears(3),
                'department' => 'Production',
                'type' => 'daily_rate',
                'daily_rate' => 1500,
                'hourly_rate' => null,
                'hours_per_day' => 8,
            ],
        ];

        foreach ($employees as $emp) {
            Employee::updateOrCreate(
                ['employee_code' => $emp['employee_code']],
                $emp
            );
        }
    }
}
