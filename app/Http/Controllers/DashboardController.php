<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use App\Models\PayrollRun;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $today       = now();
        $currentYear = $today->year;
        $currentWeek = $today->weekOfYear;

        // Employee stats
        $employeeStats = [
            'total'   => Employee::count(),
            'active'  => Employee::where('is_active', true)->count(),
            'daily'   => Employee::where('type', 'daily_rate')->count(),
            'hourly'  => Employee::where('type', 'hourly')->count(),
        ];

        // Attendance stats for current week
        $dailyLocked  = DailyRateAttendance::where('year', $currentYear)
            ->where('week_number', $currentWeek)
            ->where('locked', true)
            ->exists();

        $hourlyLocked = HourlyAttendance::where('year', $currentYear)
            ->where('week_number', $currentWeek)
            ->where('locked', true)
            ->exists();

        $attendanceStats = [
            'daily_locked'   => $dailyLocked,
            'hourly_locked'  => $hourlyLocked,
            'daily_present'  => DailyRateAttendance::where('year', $currentYear)
                ->where('week_number', $currentWeek)
                ->sum('present_days'),
            'daily_total'    => DailyRateAttendance::where('year', $currentYear)
                ->where('week_number', $currentWeek)
                ->sum('total_working_days'),
            'hourly_hours'   => HourlyAttendance::where('year', $currentYear)
                ->where('week_number', $currentWeek)
                ->sum('total_hours'),
            'hourly_ot'      => HourlyAttendance::where('year', $currentYear)
                ->where('week_number', $currentWeek)
                ->sum('overtime_hours'),
        ];

        // Latest weekly payroll run (any week)
        $latestRun = PayrollRun::with('items')
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->first();

        $payrollStats = [
            'has_run'     => (bool) $latestRun,
            'year'        => $latestRun->year ?? null,
            'week'        => $latestRun->week_number ?? null,
            'total_gross' => $latestRun?->items->sum('gross_amount') ?? 0,
            'total_cash'  => $latestRun?->items->sum('cash_amount') ?? 0,
            'total_bank'  => $latestRun?->items->sum('bank_amount') ?? 0,
        ];

        // Payroll trends (Last 12 Runs for chart)
        $payrollTrends = PayrollRun::with('items')
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->take(12)
            ->get()
            ->reverse()
            ->map(function ($run) {
                return [
                    'label' => "W{$run->week_number} '" . substr($run->year, 2),
                    'gross' => $run->items->sum('gross_amount'),
                    'cash'  => $run->items->sum('cash_amount'),
                    'bank'  => $run->items->sum('bank_amount')
                ];
            })->values();

        // Recent payroll runs (Last 6 for mini-table with week-over-week change)
        $recentRunsRaw = PayrollRun::with('items')
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->take(6)
            ->get();

        $recentRuns = $recentRunsRaw->map(function ($run, $idx) use ($recentRunsRaw) {
            $gross = $run->items->sum('gross_amount');
            $prevRun = $recentRunsRaw->get($idx + 1);
            $prevGross = $prevRun ? $prevRun->items->sum('gross_amount') : 0;
            $change = ($prevGross > 0) ? round((($gross - $prevGross) / $prevGross) * 100, 1) : null;

            return [
                'year'        => $run->year,
                'week'        => $run->week_number,
                'employees'   => $run->items->count(),
                'gross'       => $gross,
                'cash'        => $run->items->sum('cash_amount'),
                'bank'        => $run->items->sum('bank_amount'),
                'change_pct'  => $change,
            ];
        })->values();

        return view('dashboard', [
            'today'           => $today,
            'currentYear'     => $currentYear,
            'currentWeek'     => $currentWeek,
            'employeeStats'   => $employeeStats,
            'attendanceStats' => $attendanceStats,
            'payrollStats'    => $payrollStats,
            'payrollTrends'   => $payrollTrends,
            'recentRuns'      => $recentRuns,
        ]);
    }
}