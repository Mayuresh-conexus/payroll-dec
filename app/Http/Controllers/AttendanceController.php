<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use Illuminate\Http\Request;
use Carbon\Carbon;

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

    // compute ISO-week Monday and formatted date labels for each day
    $dayKeys = ['mon','tue','wed','thu','fri','sat','sun'];
    $monday = Carbon::now()->setISODate((int)$year, (int)$week, 1);
    $dayDates = [];
    foreach ($dayKeys as $i => $k) {
        $dayDates[$k] = strtoupper($monday->copy()->addDays($i)->format('d M'));
    }
        
        // compute a todayKey (mon..sun) when the selected year/week match today's ISO week
        $todayKey = null;
        $today = Carbon::now();
        if ((int) $today->isoWeek() === (int) $week && (int) $today->year === (int) $year) {
            $keys = ['mon','tue','wed','thu','fri','sat','sun'];
            $index = max(0, min(6, $today->dayOfWeekIso - 1));
            $todayKey = $keys[$index];
        }

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
        'dayDates',
           'todayKey',
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
        'attendance.*.overtime_map'  => 'nullable|array',
        'attendance.*.overtime_map.*'=> 'nullable|numeric|min:0',
    ]);

    $year = $data['year'];
    $week = $data['week'];
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
            // allow admin to mark sunday present if provided
            'sun' => isset($days['sun']) ? (int) $days['sun'] : 0,
        ];

        $presentDays = array_sum([
            $daysFull['mon'],
            $daysFull['tue'],
            $daysFull['wed'],
            $daysFull['thu'],
            $daysFull['fri'],
            $daysFull['sat'],
            $daysFull['sun'],
        ]);

        $overtimeMap = $row['overtime_map'] ?? [];
        $overtimeNormalized = [];
        $otTotal = 0.0;
        foreach (['mon','tue','wed','thu','fri','sat','sun'] as $d) {
            $v = isset($overtimeMap[$d]) ? (float)$overtimeMap[$d] : 0.0;
            $overtimeNormalized[$d] = $v;
            $otTotal += $v;
        }

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
                'overtime_map'       => $overtimeNormalized,
                'overtime_amount'    => $otTotal,
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
            'attendance.*.days'          => 'nullable|array',
            'attendance.*.days.*'        => 'in:0,1',
        // for each employee: hours_map => array with keys mon..sun, numeric; ot_map similarly
        'attendance.*.hours_map' => 'nullable|array',
        'attendance.*.hours_map.*' => 'nullable|numeric|min:0',
        'attendance.*.ot_map' => 'nullable|array',
        'attendance.*.ot_map.*' => 'nullable|numeric|min:0',
    ]);

    $year = $data['year'];
    $week = $data['week'];
    $lockWeek = isset($data['lock_week']) && (int) $data['lock_week'] === 1;

        foreach ($data['attendance'] as $employeeId => $row) {
            $hoursMap = $row['hours_map'] ?? [];
            $otMap    = $row['ot_map'] ?? [];
            $daysFlag = $row['days'] ?? [];

            // normalize keys mon..sun and ensure numeric values
            $days = ['mon','tue','wed','thu','fri','sat','sun'];
            $hoursMapNormalized = [];
            $otMapNormalized = [];
            $total = 0.0;
            $otTotal = 0.0;

            // get employee default hours if available
            $employee = Employee::find($employeeId);
            $defaultHours = $employee ? (float) ($employee->hours_per_day ?? 0) : 0.0;

            foreach ($days as $day) {
                // determine presence: prefer explicit days flag when provided
                $present = isset($daysFlag[$day]) ? (int) $daysFlag[$day] : null;

                $inputH = isset($hoursMap[$day]) ? (float) $hoursMap[$day] : null;
                $inputO = isset($otMap[$day]) ? (float) $otMap[$day] : 0.0;

                if ($present === null) {
                    // no explicit present flag: derive from provided hours (present if hours > 0)
                    $present = ($inputH !== null && $inputH > 0) ? 1 : 0;
                }

                if ($present) {
                    // if present and input hours provided use it, otherwise default to employee hours_per_day
                    // allow sunday to use default hours when admin marks present
                    $h = ($inputH !== null) ? $inputH : $defaultHours;
                } else {
                    // absent
                    $h = 0.0;
                }

                // compute extra overtime (hours beyond default) and store full hours
                $extraOt = ($h > $defaultHours) ? ($h - $defaultHours) : 0.0;
                $storedHours = $h; // store actual hours worked

                $o = $inputO + $extraOt;

                $hoursMapNormalized[$day] = $storedHours;
                $otMapNormalized[$day] = $o;
                $total += $storedHours;
                $otTotal += $o;
            }

            // store regular hours (exclude overtime) and keep overtime separately
            $regularHours = $total - $otTotal;
            if ($regularHours < 0) {
                $regularHours = 0; // safety
            }

            HourlyAttendance::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'year'        => $year,
                    'week_number' => $week,
                ],
                [
                    'hours_map'      => $hoursMapNormalized,
                    'ot_map'         => $otMapNormalized,
                    'total_hours'    => $regularHours,
                    'overtime_hours' => $otTotal,
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
/**
 * Save combined attendance for both daily-rate and hourly employees.
 */
public function storeCombined(Request $request)
{
    $data = $request->validate([
        'year'                       => 'required|integer',
        'week'                       => 'required|integer|min:1|max:52',
        'lock_week'                  => 'nullable|boolean',
        'attendance'                 => 'required|array',
        // daily
        'attendance.*.days'          => 'nullable|array',
        'attendance.*.days.*'        => 'in:0,1',
        'attendance.*.overtime_map'  => 'nullable|array',
        'attendance.*.overtime_map.*'=> 'nullable|numeric|min:0',
        // hourly
        'attendance.*.hours_map'     => 'nullable|array',
        'attendance.*.hours_map.*'   => 'nullable|numeric|min:0',
        'attendance.*.ot_map'        => 'nullable|array',
        'attendance.*.ot_map.*'      => 'nullable|numeric|min:0',
    ]);

    $year = $data['year'];
    $week = $data['week'];
    $lockWeek = isset($data['lock_week']) && (int) $data['lock_week'] === 1;

    foreach ($data['attendance'] as $employeeId => $row) {
        $employee = Employee::find($employeeId);
        if (! $employee) {
            continue;
        }

        // Normalize day keys
        $daysKeys = ['mon','tue','wed','thu','fri','sat','sun'];

        if ($employee->type === 'daily_rate') {
            // present map (mon..sat; sunday forced to 0)
            $days = $row['days'] ?? [];
            $daysFull = [];
            foreach ($daysKeys as $d) {
                // allow admin to mark sunday present if provided; otherwise default to 0 for sunday
                if ($d === 'sun') {
                    $daysFull[$d] = isset($days[$d]) ? (int) $days[$d] : 0;
                } else {
                    $daysFull[$d] = isset($days[$d]) ? (int) $days[$d] : 0;
                }
            }
            $presentDays = array_sum([
                $daysFull['mon'],
                $daysFull['tue'],
                $daysFull['wed'],
                $daysFull['thu'],
                $daysFull['fri'],
                $daysFull['sat'],
                $daysFull['sun'],
            ]);

            // overtime per day
            $overtimeMap = $row['overtime_map'] ?? [];
            $overtimeNormalized = [];
            $otTotal = 0.0;
            foreach ($daysKeys as $d) {
                $v = isset($overtimeMap[$d]) ? (float) $overtimeMap[$d] : 0.0;
                $overtimeNormalized[$d] = $v;
                $otTotal += $v;
            }

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
                    'overtime_map'       => $overtimeNormalized,
                    'overtime_amount'    => $otTotal,
                    'locked'             => $lockWeek,
                ]
            );
        } else { // hourly
            $hoursMap = $row['hours_map'] ?? [];
            $otMap = $row['ot_map'] ?? [];
            $daysFlag = $row['days'] ?? [];

            $hoursNormalized = [];
            $otNormalized = [];
            $total = 0.0;
            $otTotal = 0.0;

            // default hours for non-sunday days come from employee.hours_per_day (if set)
            $defaultHours = (float) ($employee->hours_per_day ?? 0);

            foreach ($daysKeys as $d) {
                // presence flag preference
                $present = isset($daysFlag[$d]) ? (int) $daysFlag[$d] : null;

                $inputH = isset($hoursMap[$d]) ? (float) $hoursMap[$d] : null;
                $inputO = isset($otMap[$d]) ? (float) $otMap[$d] : 0.0;

                if ($present === null) {
                    $present = ($inputH !== null && $inputH > 0) ? 1 : 0;
                }

                if ($present) {
                    // if present and input hours provided use it, otherwise default to employee hours_per_day
                    $h = ($inputH !== null) ? $inputH : $defaultHours;
                } else {
                    $h = 0.0;
                }

                // compute extra overtime (hours beyond default) and store full hours
                $extraOt = ($h > $defaultHours) ? ($h - $defaultHours) : 0.0;
                $storedHours = $h; // store actual hours worked

                $o = $inputO + $extraOt;

                $hoursNormalized[$d] = $storedHours;
                $otNormalized[$d] = $o;
                $total += $storedHours;
                $otTotal += $o;
            }

            // save regular hours excluding overtime
            $regularHours = $total - $otTotal;
            if ($regularHours < 0) {
                $regularHours = 0;
            }

            HourlyAttendance::updateOrCreate(
                [
                    'employee_id' => $employeeId,
                    'year'        => $year,
                    'week_number' => $week,
                ],
                [
                    'hours_map'      => $hoursNormalized,
                    'ot_map'         => $otNormalized,
                    'total_hours'    => $regularHours,
                    'overtime_hours' => $otTotal,
                    'locked'         => $lockWeek,
                ]
            );
        }
    }

    // mark weeks locked
    DailyRateAttendance::where('year', $year)
        ->where('week_number', $week)
        ->update(['locked' => $lockWeek]);

    HourlyAttendance::where('year', $year)
        ->where('week_number', $week)
        ->update(['locked' => $lockWeek]);

    return redirect()->route('attendance.index', [
        'year' => $year,
        'week' => $week,
        'tab'  => 'daily',
    ])->with('success', 'Attendance saved');
}
}
