<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(Request $request)
{
    $year = $request->input('year', now()->year);
    $week = $request->input('week', now()->weekOfYear);
    $tab  = $request->input('tab', 'daily'); // daily or hourly

    $dailyEmployees  = Employee::where('type', 'daily_rate')->where('is_active', true)->orderBy('name')->get();
    $hourlyEmployees = Employee::where('type', 'hourly')->where('is_active', true)->orderBy('name')->get();

    $dailyAttendances = DailyRateAttendance::where('year', $year)
        ->where('week_number', $week)
        ->get()
        ->keyBy('employee_id');

    $hourlyAttendances = HourlyAttendance::where('year', $year)
        ->where('week_number', $week)
        ->get()
        ->keyBy('employee_id');

    // week lock flags
    $dailyWeekLocked = DailyRateAttendance::where('year', $year)
        ->where('week_number', $week)
        ->where('locked', true)
        ->exists();

    $hourlyWeekLocked = HourlyAttendance::where('year', $year)
        ->where('week_number', $week)
        ->where('locked', true)
        ->exists();

    return view('attendance.index', compact(
        'year',
        'week',
        'tab',
        'dailyEmployees',
        'hourlyEmployees',
        'dailyAttendances',
        'hourlyAttendances',
        'dailyWeekLocked',
        'hourlyWeekLocked',
    ));
}


public function storeDailyRate(Request $request)
{
    $data = $request->validate([
        'year'                       => 'required|integer',
        'week'                       => 'required|integer|min:1|max:52',
        'lock_week'                  => 'nullable|boolean',
        'attendance'                 => 'required|array',
        'attendance.*.days'          => 'required|array',
        'attendance.*.days.*'        => 'in:0,1',
    ]);

    $year     = $data['year'];
    $week     = $data['week'];

    // proper boolean handling
    $lockWeek = isset($data['lock_week']) && (int) $data['lock_week'] === 1;

    foreach ($data['attendance'] as $employeeId => $row) {
        $days = $row['days'] ?? [];

        $daysFull = [
            'mon' => isset($days['mon']) ? (int) $days['mon'] : 0,
            'tue' => isset($days['tue']) ? (int) $days['tue'] : 0,
            'wed' => isset($days['wed']) ? (int) $days['wed'] : 0,
            'thu' => isset($days['thu']) ? (int) $days['thu'] : 0,
            'fri' => isset($days['fri']) ? (int) $days['fri'] : 0,
            'sat' => isset($days['sat']) ? (int) $days['sat'] : 0,
            'sun' => 0,
        ];

        $presentDays = $daysFull['mon']
            + $daysFull['tue']
            + $daysFull['wed']
            + $daysFull['thu']
            + $daysFull['fri']
            + $daysFull['sat'];

        DailyRateAttendance::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'year'        => $year,
                'week_number' => $week,
            ],
            [
                'total_working_days' => 6,
                'present_days'       => $presentDays,
                'days_map'           => $daysFull,
                'locked'             => $lockWeek,
            ]
        );
    }

    DailyRateAttendance::where('year', $year)
        ->where('week_number', $week)
        ->update(['locked' => $lockWeek]);

    return redirect()->route('attendance.index', [
        'year' => $year,
        'week' => $week,
        'tab'  => 'daily',
    ])->with('success', 'Daily rate attendance saved');
}

public function storeHourly(Request $request)
{
    $data = $request->validate([
        'year'               => 'required|integer',
        'week'               => 'required|integer|min:1|max:52',
        'lock_week'          => 'nullable|boolean',
        'attendance'         => 'required|array',
        'attendance.*.hours' => 'nullable|numeric|min:0',
        'attendance.*.ot'    => 'nullable|numeric|min:0',
    ]);

    $year     = $data['year'];
    $week     = $data['week'];

    // proper boolean handling
    $lockWeek = isset($data['lock_week']) && (int) $data['lock_week'] === 1;

    foreach ($data['attendance'] as $employeeId => $row) {
        $totalHours = $row['hours'] ?? 0;
        $otHours    = $row['ot'] ?? 0;

        HourlyAttendance::updateOrCreate(
            [
                'employee_id' => $employeeId,
                'year'        => $year,
                'week_number' => $week,
            ],
            [
                'total_hours'    => $totalHours,
                'overtime_hours' => $otHours,
                'locked'         => $lockWeek,
            ]
        );
    }

    HourlyAttendance::where('year', $year)
        ->where('week_number', $week)
        ->update(['locked' => $lockWeek]);

    return redirect()->route('attendance.index', [
        'year' => $year,
        'week' => $week,
        'tab'  => 'hourly',
    ])->with('success', 'Hourly attendance saved');
}

}
