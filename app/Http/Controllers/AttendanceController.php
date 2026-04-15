<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use App\Models\PayrollRun;
use App\Services\AttendanceService;
use App\Http\Requests\StoreAttendanceRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AttendanceController extends Controller
{
    public function index(Request $request)
{
    $year = $request->input('year', now()->year);
    $week = $request->input('week', now()->weekOfYear);
    $tab  = $request->input('tab', 'daily'); // daily or hourly
    $weeksInYear = Carbon::create($year, 12, 28)->isoWeek(); // to check 53 weeks

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




    // Load previous week attendance (for copy P/A)

        $prevWeek = $week - 1;
        $prevYear = $year;

        if ($prevWeek < 1) {
            $prevYear--;
            $prevWeek = Carbon::create($prevYear, 12, 28)->isoWeek();
        }

        $prevDailyAttendances = DailyRateAttendance::where('year', $prevYear)
            ->where('week_number', $prevWeek)
            ->get()
            ->keyBy('employee_id');

        $prevHourlyAttendances = HourlyAttendance::where('year', $prevYear)
            ->where('week_number', $prevWeek)
            ->get()
            ->keyBy('employee_id');


        // MISSING-05: Check if payroll has already been run for this week
        $weekHasPayroll = PayrollRun::where('year', $year)
            ->where('week_number', $week)
            ->where('period_type', 'weekly')
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
        'weeksInYear',
        'prevDailyAttendances',
        'prevHourlyAttendances',
        'weekHasPayroll'
    ));
}


    public function storeDailyRate(StoreAttendanceRequest $request, AttendanceService $service)
    {
        $data     = $request->validated();
        $year     = $data['year'];
        $week     = $data['week'];
        $lockWeek = isset($data['lock_week']) && (int) $data['lock_week'] === 1;

        DB::transaction(function () use ($data, $year, $week, $lockWeek, $service) {
            foreach ($data['attendance'] as $employeeId => $row) {
                $service->saveDailyEmployee($employeeId, $row, $year, $week, $lockWeek);
            }

            DailyRateAttendance::where('year', $year)
                ->where('week_number', $week)
                ->update(['locked' => $lockWeek]);
        });

        return redirect()->route('attendance.index', [
            'year' => $year,
            'week' => $week,
            'tab'  => 'daily',
        ])->with('success', 'Daily rate attendance saved');
    }


    public function storeHourly(StoreAttendanceRequest $request, AttendanceService $service)
    {
        $data     = $request->validated();
        $year     = $data['year'];
        $week     = $data['week'];
        $lockWeek = isset($data['lock_week']) && (int) $data['lock_week'] === 1;

        DB::transaction(function () use ($data, $year, $week, $lockWeek, $service) {
            foreach ($data['attendance'] as $employeeId => $row) {
                $service->saveHourlyEmployee($employeeId, $row, $year, $week, $lockWeek);
            }

            HourlyAttendance::where('year', $year)
                ->where('week_number', $week)
                ->update(['locked' => $lockWeek]);
        });

        return redirect()->route('attendance.index', [
            'year' => $year,
            'week' => $week,
            'tab'  => 'hourly',
        ])->with('success', 'Hourly attendance saved');
    }

/**
 * Save combined attendance for both daily-rate and hourly employees.
 */
    public function storeCombined(StoreAttendanceRequest $request, AttendanceService $service)
    {
        $data     = $request->validated();
        $year     = $data['year'];
        $week     = $data['week'];
        $lockWeek = isset($data['lock_week']) && (int) $data['lock_week'] === 1;

        DB::transaction(function () use ($data, $year, $week, $lockWeek, $service) {
            foreach ($data['attendance'] as $employeeId => $row) {
                $employee = Employee::find($employeeId);
                if (! $employee) {
                    continue;
                }

                if ($employee->type === 'daily_rate') {
                    $service->saveDailyEmployee($employeeId, $row, $year, $week, $lockWeek);
                } else {
                    $service->saveHourlyEmployee($employeeId, $row, $year, $week, $lockWeek);
                }
            }

            DailyRateAttendance::where('year', $year)
                ->where('week_number', $week)
                ->update(['locked' => $lockWeek]);

            HourlyAttendance::where('year', $year)
                ->where('week_number', $week)
                ->update(['locked' => $lockWeek]);
        });

        return redirect()->route('attendance.index', [
            'year' => $year,
            'week' => $week,
            'tab'  => 'daily',
        ])->with('success', 'Attendance saved');
    }
}
