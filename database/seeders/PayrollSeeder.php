<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use App\Models\PayrollRun;
use App\Models\PayrollItem;
use Carbon\Carbon;

class PayrollSeeder extends Seeder
{
    public function run(): void
    {
        $weeks = [];
        for ($w = 49; $w <= 52; $w++) {
            $weeks[] = ["year" => 2025, "week" => $w];
        }
        for ($w = 1; $w <= 8; $w++) {
            $weeks[] = ["year" => 2026, "week" => $w];
        }

        $dailyEmployees  = Employee::where("type", "daily_rate")->where("is_active", true)->get();
        $hourlyEmployees = Employee::where("type", "hourly")->where("is_active", true)->get();

        foreach ($weeks as $wk) {
            $year = $wk["year"];
            $week = $wk["week"];

            if ($year == now()->year && $week == now()->weekOfYear) continue;

            $run = PayrollRun::updateOrCreate(
                [
                    "period_type"  => "weekly",
                    "year"         => $year,
                    "week_number"  => $week,
                ],
                [
                    "month"        => null,
                    "status"       => "final",
                    "created_by"   => 1,
                    "generated_at" => now(),
                ]
            );

            foreach ($dailyEmployees as $emp) {
                $att = DailyRateAttendance::where("employee_id", $emp->id)
                    ->where("year", $year)
                    ->where("week_number", $week)
                    ->first();
                if (!$att) continue;

                $dailyRate    = $emp->daily_rate ?? 0;
                $presentDays  = $att->present_days ?? 0;
                $totalDays    = $att->total_working_days ?? 6;
                $otAmount     = (float) ($att->overtime_amount ?? 0);

                $weeklyAmount = $dailyRate * $presentDays;
                $grossAmount  = $weeklyAmount + $otAmount;
                $bankFix      = (float) ($emp->bank_transfer_fix_amount ?? 0);
                $bankAmount   = min($grossAmount, $bankFix);
                $cashAmount   = $grossAmount - $bankAmount;

                PayrollItem::updateOrCreate(
                    ["payroll_run_id" => $run->id, "employee_id" => $emp->id],
                    [
                        "type"                 => "daily_rate",
                        "present_days"         => $presentDays,
                        "total_days"           => $totalDays,
                        "total_hours"          => null,
                        "overtime_hours"       => null,
                        "overtime_amount"      => $otAmount,
                        "weekly_amount"        => $weeklyAmount,
                        "addons"               => null,
                        "gross_amount"         => $grossAmount,
                        "cash_amount"          => $cashAmount,
                        "bank_amount"          => $bankAmount,
                        "applied_daily_rate"   => $dailyRate,
                        "applied_hourly_rate"  => null,
                        "applied_hours_per_day" => $emp->hours_per_day,
                        "is_paid"              => true,
                    ]
                );
            }

            foreach ($hourlyEmployees as $emp) {
                $att = HourlyAttendance::where("employee_id", $emp->id)
                    ->where("year", $year)
                    ->where("week_number", $week)
                    ->first();
                if (!$att) continue;

                $hourlyRate    = $emp->hourly_rate ?? 0;
                $totalHours    = (float) ($att->total_hours ?? 0);
                $overtimeHours = (float) ($att->overtime_hours ?? 0);
                $otRate        = $hourlyRate * 1.5;

                $weeklyAmount = $hourlyRate * $totalHours;
                $otAmount     = $otRate * $overtimeHours;
                $grossAmount  = $weeklyAmount + $otAmount;
                $bankFix      = (float) ($emp->bank_transfer_fix_amount ?? 0);
                $bankAmount   = min($grossAmount, $bankFix);
                $cashAmount   = $grossAmount - $bankAmount;

                PayrollItem::updateOrCreate(
                    ["payroll_run_id" => $run->id, "employee_id" => $emp->id],
                    [
                        "type"                 => "hourly",
                        "present_days"         => null,
                        "total_days"           => null,
                        "total_hours"          => $totalHours,
                        "overtime_hours"       => $overtimeHours,
                        "overtime_amount"      => $otAmount,
                        "weekly_amount"        => $weeklyAmount,
                        "addons"               => null,
                        "gross_amount"         => $grossAmount,
                        "cash_amount"          => $cashAmount,
                        "bank_amount"          => $bankAmount,
                        "applied_daily_rate"   => null,
                        "applied_hourly_rate"  => $hourlyRate,
                        "applied_hours_per_day" => $emp->hours_per_day,
                        "is_paid"              => true,
                    ]
                );
            }
        }
    }
}
