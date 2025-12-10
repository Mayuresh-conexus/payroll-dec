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

        $dailyEmployees = Employee::where('type', 'daily_rate')->where('is_active', true)->orderBy('name')->get();
        $hourlyEmployees = Employee::where('type', 'hourly')->where('is_active', true)->orderBy('name')->get();

        $dailyAttendances = DailyRateAttendance::where('year', $year)
            ->where('week_number', $week)
            ->get()
            ->keyBy('employee_id');

        $hourlyAttendances = HourlyAttendance::where('year', $year)
            ->where('week_number', $week)
            ->get()
            ->keyBy('employee_id');

        return view('attendance.index', compact(
            'year',
            'week',
            'tab',
            'dailyEmployees',
            'hourlyEmployees',
            'dailyAttendances',
            'hourlyAttendances'
        ));
    }

    public function storeDailyRate(Request $request)
    {
        $data = $request->validate([
            'year'                     => 'required|integer',
            'week'                     => 'required|integer|min:1|max:52',
            'attendance'               => 'required|array',
            'attendance.*.present_days'=> 'required|integer|min:0|max:6',
        ]);

        $year = $data['year'];
        $week = $data['week'];

        foreach ($data['attendance'] as $employeeId => $row) {
            DailyRateAttendance::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'year'        => $year,
                    'week_number' => $week,
                ],
                [
                    'total_working_days' => 6,
                    'present_days'       => $row['present_days'],
                    'locked'             => false,
                ]
            );
        }

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
            'attendance'         => 'required|array',
            'attendance.*.hours' => 'nullable|numeric|min:0',
            'attendance.*.ot'    => 'nullable|numeric|min:0',
        ]);

        $year = $data['year'];
        $week = $data['week'];

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
                    'locked'         => false,
                ]
            );
        }

        return redirect()->route('attendance.index', [
            'year' => $year,
            'week' => $week,
            'tab'  => 'hourly',
        ])->with('success', 'Hourly attendance saved');
    }
}
