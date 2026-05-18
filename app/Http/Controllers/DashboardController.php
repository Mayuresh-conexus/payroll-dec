<?php

namespace App\Http\Controllers;

use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\HourlyAttendance;
use App\Models\PayrollRun;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = now();
        $currentYear = $today->year;
        $currentWeek = $today->weekOfYear;

        if (auth()->user()->hasRole('manager')) {
            return $this->managerDashboard($today, $currentYear, $currentWeek);
        }

        return $this->adminDashboard($today, $currentYear, $currentWeek);
    }

    private function adminDashboard(Carbon $today, int $currentYear, int $currentWeek): View
    {
        $employeeStats = [
            'total' => Employee::count(),
            'active' => Employee::where('is_active', true)->count(),
            'daily' => Employee::where('type', 'daily_rate')->count(),
            'hourly' => Employee::where('type', 'hourly')->count(),
        ];

        $attendanceStats = [
            'daily_locked' => DailyRateAttendance::where('year', $currentYear)->where('week_number', $currentWeek)->where('locked', true)->exists(),
            'hourly_locked' => HourlyAttendance::where('year', $currentYear)->where('week_number', $currentWeek)->where('locked', true)->exists(),
            'daily_present' => DailyRateAttendance::where('year', $currentYear)->where('week_number', $currentWeek)->sum('present_days'),
            'daily_total' => DailyRateAttendance::where('year', $currentYear)->where('week_number', $currentWeek)->sum('total_working_days'),
            'hourly_hours' => HourlyAttendance::where('year', $currentYear)->where('week_number', $currentWeek)->sum('total_hours'),
            'hourly_ot' => HourlyAttendance::where('year', $currentYear)->where('week_number', $currentWeek)->sum('overtime_hours'),
        ];

        $latestRun = PayrollRun::with('items')->orderByDesc('year')->orderByDesc('week_number')->first();

        $payrollStats = [
            'has_run' => (bool) $latestRun,
            'year' => $latestRun->year ?? null,
            'week' => $latestRun->week_number ?? null,
            'status' => $latestRun?->status ?? null,
            'total_gross' => $latestRun?->items->sum('gross_amount') ?? 0,
            'total_cash' => $latestRun?->items->sum('cash_amount') ?? 0,
            'total_bank' => $latestRun?->items->sum('bank_amount') ?? 0,
        ];

        $recentRuns = PayrollRun::with('items')
            ->orderByDesc('year')->orderByDesc('week_number')->limit(8)->get()
            ->map(fn ($run) => [
                'year' => $run->year,
                'week' => $run->week_number,
                'status' => $run->status,
                'total_gross' => $run->items->sum('gross_amount'),
                'total_cash' => $run->items->sum('cash_amount'),
                'total_bank' => $run->items->sum('bank_amount'),
                'employees' => $run->items->count(),
            ]);

        return view('dashboard', [
            'today' => $today,
            'currentYear' => $currentYear,
            'currentWeek' => $currentWeek,
            'isManagerView' => false,
            'employeeStats' => $employeeStats,
            'attendanceStats' => $attendanceStats,
            'payrollStats' => $payrollStats,
            'recentRuns' => $recentRuns,
            'teamCards' => collect(),
            'dayKeys' => [],
            'dayDates' => [],
            'markedCount' => 0,
            'unmarkedCount' => 0,
        ]);
    }

    private function managerDashboard(Carbon $today, int $currentYear, int $currentWeek): View
    {
        $manager = auth()->user();
        $assignedEmployees = $manager->assignedEmployees()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $employeeIds = $assignedEmployees->pluck('id');

        // Bulk-fetch this week's attendance — no N+1
        $dailyAttendances = DailyRateAttendance::where('year', $currentYear)
            ->where('week_number', $currentWeek)
            ->whereIn('employee_id', $employeeIds)
            ->get()->keyBy('employee_id');

        $hourlyAttendances = HourlyAttendance::where('year', $currentYear)
            ->where('week_number', $currentWeek)
            ->whereIn('employee_id', $employeeIds)
            ->get()->keyBy('employee_id');

        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $monday = now()->setISODate($currentYear, $currentWeek, 1);
        $dayDates = [];
        foreach ($dayKeys as $i => $k) {
            $dayDates[$k] = $monday->copy()->addDays($i)->format('d M');
        }

        $teamCards = $assignedEmployees->map(
            fn ($emp) => $emp->type === 'daily_rate'
                ? $this->buildDailyCard($emp, $dailyAttendances->get($emp->id))
                : $this->buildHourlyCard($emp, $hourlyAttendances->get($emp->id))
        );

        return view('dashboard', [
            'today' => $today,
            'currentYear' => $currentYear,
            'currentWeek' => $currentWeek,
            'isManagerView' => true,
            'teamCards' => $teamCards,
            'dayKeys' => $dayKeys,
            'dayDates' => $dayDates,
            'markedCount' => $teamCards->where('marked', true)->count(),
            'unmarkedCount' => $teamCards->where('marked', false)->count(),
            // Nulled-out admin-only variables (unused by manager view)
            'employeeStats' => null,
            'attendanceStats' => ['daily_locked' => false, 'hourly_locked' => false, 'daily_present' => 0, 'daily_total' => 0, 'hourly_hours' => 0, 'hourly_ot' => 0],
            'payrollStats' => ['has_run' => false],
            'recentRuns' => collect(),
        ]);
    }

    private function buildDailyCard(Employee $emp, ?DailyRateAttendance $att): array
    {
        return [
            'employee' => $emp,
            'type' => 'daily',
            'marked' => (bool) $att,
            'days_map' => $att?->days_map ?? [],
            'present' => $att?->present_days ?? 0,
            'total' => $att?->total_working_days ?? 0,
            'rate_label' => number_format((float) $emp->daily_rate, 2).'/day',
            'locked' => $att?->locked ?? false,
        ];
    }

    private function buildHourlyCard(Employee $emp, ?HourlyAttendance $att): array
    {
        return [
            'employee' => $emp,
            'type' => 'hourly',
            'marked' => (bool) $att,
            'hours_map' => $att?->hours_map ?? [],
            'total_hours' => $att?->total_hours ?? 0,
            'ot_hours' => $att?->overtime_hours ?? 0,
            'rate_label' => number_format((float) $emp->hourly_rate, 2).'/hr',
            'locked' => $att?->locked ?? false,
        ];
    }
}
