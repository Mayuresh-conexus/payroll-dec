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
            // ── Daily Rate (CTC) employees ──────────────────────
            ['employee_code' => 'DR001', 'name' => 'Rajesh Kumar', 'joining_date' => Carbon::now()->subYears(3)->subMonths(2), 'department' => 'Production', 'type' => 'daily_rate', 'daily_rate' => 1200, 'hourly_rate' => null, 'hours_per_day' => 8, 'bank_transfer_fix_amount' => 500, 'weekly_active_days' => 6],
            ['employee_code' => 'DR002', 'name' => 'Priya Sharma', 'joining_date' => Carbon::now()->subYears(2), 'department' => 'HR', 'type' => 'daily_rate', 'daily_rate' => 900, 'hourly_rate' => null, 'hours_per_day' => 8, 'bank_transfer_fix_amount' => 400, 'weekly_active_days' => 6],
            ['employee_code' => 'DR003', 'name' => 'Amit Patel', 'joining_date' => Carbon::now()->subMonths(14), 'department' => 'Production', 'type' => 'daily_rate', 'daily_rate' => 1000, 'hourly_rate' => null, 'hours_per_day' => 8, 'bank_transfer_fix_amount' => 450, 'weekly_active_days' => 6],
            ['employee_code' => 'DR004', 'name' => 'Sunita Devi', 'joining_date' => Carbon::now()->subYears(4), 'department' => 'Accounts', 'type' => 'daily_rate', 'daily_rate' => 1500, 'hourly_rate' => null, 'hours_per_day' => 8, 'bank_transfer_fix_amount' => 600, 'weekly_active_days' => 6],
            ['employee_code' => 'DR005', 'name' => 'Vikram Singh', 'joining_date' => Carbon::now()->subMonths(8), 'department' => 'Production', 'type' => 'daily_rate', 'daily_rate' => 800, 'hourly_rate' => null, 'hours_per_day' => 8, 'bank_transfer_fix_amount' => 350, 'weekly_active_days' => 6],
            ['employee_code' => 'DR006', 'name' => 'Meena Gupta', 'joining_date' => Carbon::now()->subYears(1)->subMonths(3), 'department' => 'Quality', 'type' => 'daily_rate', 'daily_rate' => 1100, 'hourly_rate' => null, 'hours_per_day' => 8, 'bank_transfer_fix_amount' => 500, 'weekly_active_days' => 6],
            ['employee_code' => 'DR007', 'name' => 'Arjun Yadav', 'joining_date' => Carbon::now()->subMonths(5), 'department' => 'Maintenance', 'type' => 'daily_rate', 'daily_rate' => 750, 'hourly_rate' => null, 'hours_per_day' => 8, 'bank_transfer_fix_amount' => 300, 'weekly_active_days' => 6],
            ['employee_code' => 'DR008', 'name' => 'Kavita Joshi', 'joining_date' => Carbon::now()->subYears(2)->subMonths(6), 'department' => 'Admin', 'type' => 'daily_rate', 'daily_rate' => 1300, 'hourly_rate' => null, 'hours_per_day' => 8, 'bank_transfer_fix_amount' => 550, 'weekly_active_days' => 6],
            ['employee_code' => 'DR009', 'name' => 'Manoj Tiwari', 'joining_date' => Carbon::now()->subMonths(10), 'department' => 'Production', 'type' => 'daily_rate', 'daily_rate' => 950, 'hourly_rate' => null, 'hours_per_day' => 8, 'bank_transfer_fix_amount' => 400, 'weekly_active_days' => 6],

            // ── Hourly employees ────────────────────────────────
            ['employee_code' => 'HR001', 'name' => 'Deepak Chauhan', 'joining_date' => Carbon::now()->subYears(1), 'department' => 'Warehouse', 'type' => 'hourly', 'daily_rate' => null, 'hourly_rate' => 120, 'hours_per_day' => 8, 'bank_transfer_fix_amount' => 300, 'weekly_active_days' => 6],
            ['employee_code' => 'HR002', 'name' => 'Anita Verma', 'joining_date' => Carbon::now()->subMonths(7), 'department' => 'Warehouse', 'type' => 'hourly', 'daily_rate' => null, 'hourly_rate' => 100, 'hours_per_day' => 8, 'bank_transfer_fix_amount' => 250, 'weekly_active_days' => 6],
            ['employee_code' => 'HR003', 'name' => 'Ravi Prakash', 'joining_date' => Carbon::now()->subYears(2)->subMonths(4), 'department' => 'Maintenance', 'type' => 'hourly', 'daily_rate' => null, 'hourly_rate' => 150, 'hours_per_day' => 7.5, 'bank_transfer_fix_amount' => 400, 'weekly_active_days' => 6],
            ['employee_code' => 'HR004', 'name' => 'Pooja Nair', 'joining_date' => Carbon::now()->subMonths(3), 'department' => 'Packaging', 'type' => 'hourly', 'daily_rate' => null, 'hourly_rate' => 90, 'hours_per_day' => 8, 'bank_transfer_fix_amount' => 200, 'weekly_active_days' => 6],
            ['employee_code' => 'HR005', 'name' => 'Sanjay Mishra', 'joining_date' => Carbon::now()->subYears(1)->subMonths(6), 'department' => 'Warehouse', 'type' => 'hourly', 'daily_rate' => null, 'hourly_rate' => 130, 'hours_per_day' => 8, 'bank_transfer_fix_amount' => 350, 'weekly_active_days' => 6],
            ['employee_code' => 'HR006', 'name' => 'Neha Pandey', 'joining_date' => Carbon::now()->subMonths(9), 'department' => 'Maintenance', 'type' => 'hourly', 'daily_rate' => null, 'hourly_rate' => 110, 'hours_per_day' => 7.5, 'bank_transfer_fix_amount' => 280, 'weekly_active_days' => 6],
        ];

        foreach ($employees as $emp) {
            Employee::updateOrCreate(
            ['employee_code' => $emp['employee_code']],
                $emp
            );
        }
    }
}
