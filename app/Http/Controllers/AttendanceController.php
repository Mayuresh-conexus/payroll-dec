<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAttendanceRequest;
use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\HourlyAttendance;
use App\Models\PayrollRun;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceController extends Controller
{
    public function index(Request $request)
    {
        $year = $request->input('year', now()->year);
        $week = $request->input('week', now()->weekOfYear);
        $tab = $request->input('tab', 'daily');
        $weeksInYear = Carbon::create($year, 12, 28)->isoWeek();

        $user = auth()->user();

        if ($user->hasRole('admin')) {
            $memberFilter = $request->input('member_filter', 'all'); // all | managers | employees
            $baseQuery = Employee::where('is_active', true)->orderBy('name');

            if ($memberFilter === 'managers') {
                $baseQuery->whereHas('user', fn ($q) => $q->where('role', 'manager'));
            } elseif ($memberFilter === 'employees') {
                $baseQuery->whereDoesntHave('user');
            }

            $dailyEmployees = (clone $baseQuery)->where('type', 'daily_rate')->get();
            $hourlyEmployees = (clone $baseQuery)->where('type', 'hourly')->get();
        } else {
            $memberFilter = 'all';
            $assigned = Employee::forManager($user->id)->where('is_active', true)->orderBy('name')->get();
            $dailyEmployees = $assigned->where('type', 'daily_rate')->values();
            $hourlyEmployees = $assigned->where('type', 'hourly')->values();
        }

        $dailyAttendances = DailyRateAttendance::where('year', $year)
            ->where('week_number', $week)
            ->get()
            ->keyBy('employee_id');

        $hourlyAttendances = HourlyAttendance::where('year', $year)
            ->where('week_number', $week)
            ->get()
            ->keyBy('employee_id');

        // compute ISO-week Monday and formatted date labels for each day
        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $monday = Carbon::now()->setISODate((int) $year, (int) $week, 1);
        $dayDates = [];
        foreach ($dayKeys as $i => $k) {
            $dayDates[$k] = strtoupper($monday->copy()->addDays($i)->format('d M'));
        }

        // compute a todayKey (mon..sun) when the selected year/week match today's ISO week
        $todayKey = null;
        $today = Carbon::now();
        if ((int) $today->isoWeek() === (int) $week && (int) $today->year === (int) $year) {
            $keys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
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
            'weekHasPayroll',
            'memberFilter'
        ));
    }

    /**
     * Abort 403 if the authenticated manager tries to submit attendance for
     * an employee that is not in their assignment list. Admins always pass.
     */
    private function authorizeEmployeeIds(array $submittedIds): void
    {
        $user = auth()->user();

        if ($user->hasRole('admin')) {
            return;
        }

        $allowedIds = Employee::forManager($user->id)->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ($submittedIds as $id) {
            if (! in_array((int) $id, $allowedIds, true)) {
                abort(403, 'You are not authorised to manage attendance for this employee.');
            }
        }
    }

    public function storeDailyRate(StoreAttendanceRequest $request, AttendanceService $service)
    {
        $data = $request->validated();
        $year = $data['year'];
        $week = $data['week'];
        $lockWeek = isset($data['lock_week']) && (int) $data['lock_week'] === 1;

        $this->authorizeEmployeeIds(array_keys($data['attendance']));

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
            'tab' => 'daily',
        ])->with('success', 'Daily rate attendance saved');
    }

    public function storeHourly(StoreAttendanceRequest $request, AttendanceService $service)
    {
        $data = $request->validated();
        $year = $data['year'];
        $week = $data['week'];
        $lockWeek = isset($data['lock_week']) && (int) $data['lock_week'] === 1;

        $this->authorizeEmployeeIds(array_keys($data['attendance']));

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
            'tab' => 'hourly',
        ])->with('success', 'Hourly attendance saved');
    }

    /**
     * Save combined attendance for both daily-rate and hourly employees.
     */
    public function storeCombined(StoreAttendanceRequest $request, AttendanceService $service)
    {
        $data = $request->validated();
        $year = $data['year'];
        $week = $data['week'];
        $lockWeek = isset($data['lock_week']) && (int) $data['lock_week'] === 1;

        $this->authorizeEmployeeIds(array_keys($data['attendance']));

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
            'tab' => 'daily',
        ])->with('success', 'Attendance saved');
    }
}
