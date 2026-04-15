<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use App\Models\PayrollRun;

class DashboardController extends Controller
{
    public function index()
    {
        $today       = now();
        $currentYear = $today->year;
        $currentWeek = $today->weekOfYear;

        // ── Employee stats ────────────────────────────────────────────────
        $employeeStats = [
            'total'  => Employee::count(),
            'active' => Employee::where('is_active', true)->count(),
            'daily'  => Employee::where('type', 'daily_rate')->count(),
            'hourly' => Employee::where('type', 'hourly')->count(),
        ];

        // ── Attendance stats for current week ─────────────────────────────
        $dailyLocked  = DailyRateAttendance::where('year', $currentYear)
            ->where('week_number', $currentWeek)
            ->where('locked', true)->exists();

        $hourlyLocked = HourlyAttendance::where('year', $currentYear)
            ->where('week_number', $currentWeek)
            ->where('locked', true)->exists();

        $attendanceStats = [
            'daily_locked'  => $dailyLocked,
            'hourly_locked' => $hourlyLocked,
            'daily_present' => DailyRateAttendance::where('year', $currentYear)
                ->where('week_number', $currentWeek)->sum('present_days'),
            'daily_total'   => DailyRateAttendance::where('year', $currentYear)
                ->where('week_number', $currentWeek)->sum('total_working_days'),
            'hourly_hours'  => HourlyAttendance::where('year', $currentYear)
                ->where('week_number', $currentWeek)->sum('total_hours'),
            'hourly_ot'     => HourlyAttendance::where('year', $currentYear)
                ->where('week_number', $currentWeek)->sum('overtime_hours'),
        ];

        // ── Latest weekly payroll run ─────────────────────────────────────
        $latestRun = PayrollRun::with('items')
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->first();

        $payrollStats = [
            'has_run'     => (bool) $latestRun,
            'year'        => $latestRun->year        ?? null,
            'week'        => $latestRun->week_number ?? null,
            'status'      => $latestRun?->status     ?? null,
            'total_gross' => $latestRun?->items->sum('gross_amount') ?? 0,
            'total_cash'  => $latestRun?->items->sum('cash_amount')  ?? 0,
            'total_bank'  => $latestRun?->items->sum('bank_amount')  ?? 0,
        ];

        // ── Recent payroll runs (last 8 for history table) ────────────────
        $recentRuns = PayrollRun::with('items')
            ->orderByDesc('year')
            ->orderByDesc('week_number')
            ->limit(8)
            ->get()
            ->map(fn ($run) => [
                'year'        => $run->year,
                'week'        => $run->week_number,
                'status'      => $run->status,
                'total_gross' => $run->items->sum('gross_amount'),
                'total_cash'  => $run->items->sum('cash_amount'),
                'total_bank'  => $run->items->sum('bank_amount'),
                'employees'   => $run->items->count(),
            ]);

        return view('dashboard', [
            'today'           => $today,
            'currentYear'     => $currentYear,
            'currentWeek'     => $currentWeek,
            'employeeStats'   => $employeeStats,
            'attendanceStats' => $attendanceStats,
            'payrollStats'    => $payrollStats,
            'recentRuns'      => $recentRuns,
        ]);
    }
}
