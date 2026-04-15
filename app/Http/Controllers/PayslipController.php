<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Response;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PayslipController extends Controller
{
    use AuthorizesRequests;

    /**
     * Generate and stream a PDF payslip for a single employee/week.
     *
     * GET /payroll/{year}/{week}/payslip/{employee}
     */
    public function show(int $year, int $week, Employee $employee): Response
    {
        $this->authorize('viewAny', PayrollRun::class);

        // Find the payroll run for this week
        $run = PayrollRun::where('year', $year)
            ->where('week_number', $week)
            ->first();

        abort_if(! $run, 404, 'No payroll run found for this week.');

        // Find the payroll item for this employee
        $item = PayrollItem::where('payroll_run_id', $run->id)
            ->where('employee_id', $employee->id)
            ->first();

        abort_if(! $item, 404, 'No payroll record for this employee in this week.');

        // Week date range
        $weekStart = Carbon::now()->setISODate($year, $week)->startOfWeek();
        $weekEnd   = $weekStart->copy()->endOfWeek();

        // Attendance detail for context
        $dailyAtt  = DailyRateAttendance::where('year', $year)
            ->where('week_number', $week)
            ->where('employee_id', $employee->id)
            ->first();

        $hourlyAtt = HourlyAttendance::where('year', $year)
            ->where('week_number', $week)
            ->where('employee_id', $employee->id)
            ->first();

        $data = [
            'year'       => $year,
            'week'       => $week,
            'weekStart'  => $weekStart,
            'weekEnd'    => $weekEnd,
            'run'        => $run,
            'item'       => $item,
            'employee'   => $employee,
            'dailyAtt'   => $dailyAtt,
            'hourlyAtt'  => $hourlyAtt,
            'generatedAt'=> now(),
        ];

        $pdf = Pdf::loadView('payroll.payslip', $data)
            ->setPaper('a4', 'portrait');

        $filename = sprintf(
            'payslip_%s_W%02d_%d.pdf',
            str_replace(' ', '_', $employee->name),
            $week,
            $year,
        );

        return $pdf->download($filename);
    }
}
