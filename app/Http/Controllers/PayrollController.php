<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use App\Models\PayrollRun;
use App\Models\PayrollItem;
use App\Services\AttendanceService;
use App\Services\PayrollService;
use App\Http\Requests\SaveWeekPayrollRequest;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;



class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $year        = (int) $request->input('year', now()->year);
        $week        = (int) $request->input('week', now()->weekOfYear);
        $weeksInYear = Carbon::create($year, 12, 28)->isoWeek();
        $month       = Carbon::now()->setISODate($year, $week)->month;

        $dailyLockedWeek  = DailyRateAttendance::where('year', $year)
            ->where('week_number', $week)->where('locked', true)->exists();
        $hourlyLockedWeek = HourlyAttendance::where('year', $year)
            ->where('week_number', $week)->where('locked', true)->exists();

        $run = PayrollRun::with('items.employee')
            ->where('year', $year)->where('week_number', $week)->first();

        $service = app(PayrollService::class);
        $rawRows = $service->buildRowsFromAttendance($year, $week);
        $rows    = $service->mergeWithPayrollRun($rawRows, $run, $dailyLockedWeek, $hourlyLockedWeek);

        $totals = [
            'gross' => $rows->sum('gross_amount'),
            'cash'  => $rows->sum('cash_amount'),
            'bank'  => $rows->sum('bank_amount'),
        ];

        $history = $run
            ? AuditLog::where('model_type', 'PayrollRun')
                ->where('model_id', $run->id)
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
            : collect();

        return view('payroll.index', [
            'year'        => $year,
            'week'        => $week,
            'month'       => $month,
            'run'         => $run,
            'rows'        => $rows,
            'totals'      => $totals,
            'weeksInYear' => $weeksInYear,
            'history'     => $history,
        ]);
    }


    // buildRowsFromAttendance is now in PayrollService — kept here for
    // backward-compat in case any other code references it directly.
    protected function buildRowsFromAttendance(int $year, int $week)
    {
        return app(PayrollService::class)->buildRowsFromAttendance($year, $week);
    }


    public function saveWeek(SaveWeekPayrollRequest $request)
    {
        $data = $request->validated();

        $run = PayrollRun::updateOrCreate(
            ['year' => $data['year'], 'week_number' => $data['week']],
            [
                'status'       => 'draft',
                'created_by'   => auth()->id(),
                'generated_at' => now(),
            ]
        );

        // Snapshot state before save for the activity log
        $before = PayrollItem::where('payroll_run_id', $run->id)
            ->with('employee:id,name')
            ->get()
            ->keyBy('employee_id');

        foreach ($data['items'] as $row) {
            $weeklyAmount = (float) ($row['weekly_amount'] ?? 0);
            $cash         = (float) ($row['cash'] ?? 0);
            $bank         = (float) ($row['bank'] ?? 0);

            // Allow bank > weekly (advance scenario). When bank exceeds earnings, cash must be 0.
            // Otherwise cap so cash + bank never exceeds weekly.
            if ($bank > $weeklyAmount) {
                $cash = 0;
            } elseif ($cash + $bank > $weeklyAmount) {
                $bank = max(0, $weeklyAmount - $cash);
            }

            $emp       = Employee::find($row['employee_id']);
            $weekStart = Carbon::now()->setISODate($data['year'], $data['week'], 1);

            // Advance balance — running cumulative: max(0, prev + bank - earned)
            $prevBalance     = (float) ($row['prev_advance_balance'] ?? 0);
            $advanceGiven    = max(0, $bank - $weeklyAmount);
            $advanceRecovered= max(0, min($prevBalance, $weeklyAmount - $bank));
            $advanceBalance  = round(max(0, $prevBalance + $bank - $weeklyAmount), 2);

            PayrollItem::updateOrCreate(
                ['payroll_run_id' => $run->id, 'employee_id' => $row['employee_id']],
                [
                    'type'                  => $row['type'],
                    'total_days'            => $row['total_days'] ?? null,
                    'present_days'          => $row['present_days'] ?? null,
                    'total_hours'           => $row['total_hours'] ?? null,
                    'weekly_amount'         => $weeklyAmount,
                    'gross_amount'          => $weeklyAmount,
                    'cash_amount'           => $cash,
                    'bank_amount'           => $bank,
                    'overtime_amount'       => $row['type'] === 'daily_rate' ? ($row['overtime'] ?? 0) : null,
                    'overtime_hours'        => $row['type'] === 'hourly'     ? ($row['overtime'] ?? 0) : null,
                    'applied_daily_rate'    => $emp ? ($emp->rateAt($weekStart, 'daily_rate')   ?? $emp->daily_rate)   : null,
                    'applied_hourly_rate'   => $emp ? ($emp->rateAt($weekStart, 'hourly_rate')  ?? $emp->hourly_rate)  : null,
                    'applied_hours_per_day' => $emp ? ($emp->rateAt($weekStart, 'hours_per_day') ?? $emp->hours_per_day) : null,
                    'advance_given'         => round($advanceGiven,     2),
                    'advance_recovered'     => round($advanceRecovered, 2),
                    'advance_balance'       => $advanceBalance,
                ]
            );
        }

        // Build per-employee diff and write one rich audit entry
        $diffs = [];
        foreach ($data['items'] as $row) {
            $old    = $before->get($row['employee_id']);
            $name   = $old?->employee?->name ?? Employee::find($row['employee_id'])?->name ?? "Employee #{$row['employee_id']}";
            $newW   = round((float) ($row['weekly_amount'] ?? 0), 2);
            $newC   = round((float) ($row['cash']          ?? 0), 2);
            $newB   = round((float) ($row['bank']          ?? 0), 2);
            $entry  = ['name' => $name];

            if ($old) {
                $oldW = round((float) $old->weekly_amount, 2);
                $oldC = round((float) $old->cash_amount,   2);
                $oldB = round((float) $old->bank_amount,   2);
                if ($oldW !== $newW) $entry['weekly'] = ['from' => $oldW, 'to' => $newW];
                if ($oldC !== $newC) $entry['cash']   = ['from' => $oldC, 'to' => $newC];
                if ($oldB !== $newB) $entry['bank']   = ['from' => $oldB, 'to' => $newB];
            } else {
                $entry['weekly'] = ['from' => null, 'to' => $newW];
                $entry['cash']   = ['from' => null, 'to' => $newC];
                $entry['bank']   = ['from' => null, 'to' => $newB];
            }

            $diffs[] = $entry;
        }

        $this->writePayrollAudit($run->id, 'save', $diffs);

        return redirect()->route('payroll.index', [
            'year' => $data['year'],
            'week' => $data['week'],
        ])->with('success', 'Weekly payroll saved');
    }


    /**
     * Recalculate all payroll figures from current attendance data.
     * Resets cash/bank splits back to employee defaults.
     * Only allowed on draft (non-finalized) runs.
     */
    public function refreshWeek(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer',
            'week' => 'required|integer|min:1|max:53',
        ]);

        $run = PayrollRun::where('year', $data['year'])
            ->where('week_number', $data['week'])
            ->where('period_type', 'weekly')
            ->first();

        if ($run && $run->status === 'final') {
            return back()->withErrors(['refresh' => 'Cannot refresh a finalized payroll.']);
        }

        $attService = app(AttendanceService::class);

        // Snapshot before refresh for the activity log
        $before = $run
            ? PayrollItem::where('payroll_run_id', $run->id)
                ->with('employee:id,name')
                ->get()
                ->keyBy('employee_id')
            : collect();

        $dailyAtts = DailyRateAttendance::where('year', $data['year'])
            ->where('week_number', $data['week'])
            ->get();

        foreach ($dailyAtts as $att) {
            $attService->saveDailyEmployee($att->employee_id, [
                'days'         => $att->days_map    ?? [],
                'overtime_map' => $att->overtime_map ?? [],
            ], $data['year'], $data['week'], (bool) ($att->locked ?? false));
        }

        $hourlyAtts = HourlyAttendance::where('year', $data['year'])
            ->where('week_number', $data['week'])
            ->get();

        foreach ($hourlyAtts as $att) {
            $attService->saveHourlyEmployee($att->employee_id, [
                'hours_map' => $att->hours_map ?? [],
                'ot_map'    => $att->ot_map    ?? [],
                'days'      => [],
            ], $data['year'], $data['week'], (bool) ($att->locked ?? false));
        }

        // Reload after refresh and compute per-employee diffs
        $runNow = PayrollRun::where('year', $data['year'])
            ->where('week_number', $data['week'])
            ->where('period_type', 'weekly')
            ->first();

        $diffs = [];
        if ($runNow) {
            $after = PayrollItem::where('payroll_run_id', $runNow->id)
                ->with('employee:id,name')
                ->get();

            foreach ($after as $item) {
                $old   = $before->get($item->employee_id);
                $name  = $item->employee?->name ?? "Employee #{$item->employee_id}";
                $newW  = round((float) $item->weekly_amount, 2);
                $newC  = round((float) $item->cash_amount,   2);
                $newB  = round((float) $item->bank_amount,   2);
                $entry = ['name' => $name];

                if ($old) {
                    $oldW = round((float) $old->weekly_amount, 2);
                    $oldC = round((float) $old->cash_amount,   2);
                    $oldB = round((float) $old->bank_amount,   2);
                    if ($oldW !== $newW) $entry['weekly'] = ['from' => $oldW, 'to' => $newW];
                    if ($oldC !== $newC) $entry['cash']   = ['from' => $oldC, 'to' => $newC];
                    if ($oldB !== $newB) $entry['bank']   = ['from' => $oldB, 'to' => $newB];
                } else {
                    $entry['weekly'] = ['from' => null, 'to' => $newW];
                }

                $diffs[] = $entry;
            }

            $this->writePayrollAudit($runNow->id, 'recalculate', $diffs);
        }

        return redirect()->route('payroll.index', [
            'year' => $data['year'],
            'week' => $data['week'],
        ])->with('success', 'Payroll recalculated from attendance. Cash/bank splits have been reset to defaults.');
    }


    /**
     * MISSING-02: Finalize a weekly payroll run (set status = 'final').
     * Once finalized, the payroll form is read-only.
     */
    public function finalizeWeek(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer',
            'week' => 'required|integer|min:1|max:53',
        ]);

        $run = PayrollRun::where('period_type', 'weekly')
            ->where('year', $data['year'])
            ->where('week_number', $data['week'])
            ->first();

        if (!$run) {
            return back()->withErrors(['finalize' => 'No payroll run found for this week. Save payroll first.']);
        }

        $run->status = 'final';
        $run->save();

        return redirect()->route('payroll.index', [
            'year' => $data['year'],
            'week' => $data['week'],
        ])->with('success', 'Payroll for week ' . $data['week'] . ' has been finalized and is now locked.');
    }

    /**
     * Weekly payroll export CSV, now including day wise IN / OFF columns
     */
public function exportWeekCsv(Request $request)
{
    $year = (int) $request->input('year', now()->year);
    $week = (int) $request->input('week', now()->weekOfYear);

    $run = PayrollRun::with('items.employee')
        ->where('year', $year)
        ->where('week_number', $week)
        ->firstOrFail();

    $dailyAttendance = DailyRateAttendance::where('year', $year)
        ->where('week_number', $week)
        ->get()
        ->keyBy('employee_id');

    $hourlyAttendance = HourlyAttendance::where('year', $year)
        ->where('week_number', $week)
        ->get()
        ->keyBy('employee_id');

    $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    $spreadsheet = new Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();

   // WEEK title row
$sheet->setCellValue('A1', 'WEEK ' . $week . ' - ' . $year);
$sheet->mergeCells('A1:P1');
$sheet->getRowDimension(1)->setRowHeight(22);

// background of title row (light slate)
$sheet->getStyle('A1:P1')->getFill()
    ->setFillType(Fill::FILL_SOLID)
    ->getStartColor()->setARGB('FF14213D'); // E2E8F0 with FF prefix

// title font: bold, bigger, dark slate text
$sheet->getStyle('A1')->getFont()
    ->setBold(true)
    ->setSize(14)
    ->getColor()->setARGB('FFFCA311'); // 0F172A with FF prefix

// center text
$sheet->getStyle('A1')->getAlignment()
    ->setHorizontal('center')
    ->setVertical('center');



    // Row 2: dates above Mon Sun (D2 J2)
    // ISO week Monday
    $monday = Carbon::now()->setISODate($year, $week, 1);

$dateCols = ['D', 'E', 'F', 'G', 'H', 'I', 'J'];

foreach ($dateCols as $index => $col) {
    $cell = $col . '2'; // <— target Row 2 only

    $date = $monday->copy()->addDays($index);

    // Set date text
    $sheet->setCellValue($cell, strtoupper($date->format('d M')));

    // Text color black
    $sheet->getStyle($cell)->getFont()
        ->getColor()->setARGB('FF000000'); // must be ARGB with FF prefix

    // Center alignment
    $sheet->getStyle($cell)->getAlignment()
        ->setHorizontal('center')
        ->setVertical('center');
}


    // Center the date row
    $sheet->getStyle('D2:J2')->getAlignment()
        ->setHorizontal('center')
        ->setVertical('center');
    $sheet->getStyle('D2:J2')->getFont()
        ->setBold(false)
        ->setSize(10)
        ->getColor()->setARGB('4B5563'); // grayish text

    // Headings row now on row 3
    $headers = [
        'A3' => 'Employee Code',
        'B3' => 'Employee Name',
        'C3' => 'Type',
        'D3' => 'Mon',
        'E3' => 'Tue',
        'F3' => 'Wed',
        'G3' => 'Thu',
        'H3' => 'Fri',
        'I3' => 'Sat',
        'J3' => 'Sun',
        'K3' => 'Present Days',
        'L3' => 'Total Hours',
        'M3' => 'Overtime',
        'N3' => 'Weekly Total',
        'O3' => 'Cash',
        'P3' => 'Bank',
    ];

   // write header labels
foreach ($headers as $cell => $label) {
    $sheet->setCellValue($cell, $label);
    $sheet->getStyle($cell)->getFont()
    ->setBold(true);

    // merge row 3 and row 4 for each column
    $col = preg_replace('/\d/', '', $cell); // extract column letter(s)
    $sheet->mergeCells("{$col}3:{$col}4");
}

// center alignment for merged headers
$sheet->getStyle('A3:P4')->getAlignment()
    ->setHorizontal('center')
    ->setVertical('center');

    // Employee headings (A3:C3) black background, white text
    $sheet->getStyle('A3:C3')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('000000');
    $sheet->getStyle('A3:C3')->getFont()->getColor()->setARGB(Color::COLOR_WHITE);

    // Day headings (D3:J3) light gray background, black text
    $sheet->getStyle('D3:J3')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('E5E7EB');
    $sheet->getStyle('D3:J3')->getFont()->getColor()->setARGB('000000');

    // Rest headings (K3:P3) dark green background, white text
    $sheet->getStyle('K3:P3')->getFill()
        ->setFillType(Fill::FILL_SOLID)
        ->getStartColor()->setARGB('065F46');
    $sheet->getStyle('K3:P3')->getFont()->getColor()->setARGB(Color::COLOR_WHITE);

    // center headings text
    $sheet->getStyle('A3:P3')->getAlignment()
        ->setHorizontal('center')
        ->setVertical('center');

    // data starts from row 4
    $rowIndex = 5;

    foreach ($run->items as $item) {
        $emp = $item->employee;
        if (! $emp) {
            continue;
        }

        // default days map: Mon to Sat present, Sun off
        $daysMap = [
            'mon' => 1,
            'tue' => 1,
            'wed' => 1,
            'thu' => 1,
            'fri' => 1,
            'sat' => 1,
            'sun' => 0,
        ];

        if ($item->type === 'daily_rate') {
            $att = $dailyAttendance[$item->employee_id] ?? null;
            if ($att && is_array($att->days_map)) {
                $daysMap = array_merge($daysMap, $att->days_map);
            }
        } else {
            $daysMap = [
                'mon' => null,
                'tue' => null,
                'wed' => null,
                'thu' => null,
                'fri' => null,
                'sat' => null,
                'sun' => null,
            ];
        }

        // base values
        $sheet->setCellValue("A{$rowIndex}", $emp->employee_code);
        $sheet->setCellValue("B{$rowIndex}", $emp->name);
        $sheet->setCellValue("C{$rowIndex}", $item->type === 'daily_rate' ? 'Daily' : 'Hourly');

        // row default style
        $sheet->getStyle("A{$rowIndex}:P{$rowIndex}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFFF');
        $sheet->getStyle("A{$rowIndex}:P{$rowIndex}")->getFont()
            ->getColor()->setARGB(Color::COLOR_BLACK);

        // day wise values with IN / OFF / CLOSED and special colors
        $col = 'D';
        foreach ($dayKeys as $key) {
            $val  = $daysMap[$key] ?? null;
            $cell = "{$col}{$rowIndex}";

            if ($item->type !== 'daily_rate') {
                // hourly: show hours + ot per day if available
                $hAtt = $hourlyAttendance[$item->employee_id] ?? null;
                $hours = null;
                $otDay = null;
                
                if ($hAtt && is_array($hAtt->hours_map)) {
                    $hours = isset($hAtt->hours_map[$key]) ? $hAtt->hours_map[$key] : null;
                }
                if ($hAtt && is_array($hAtt->ot_map)) {
                    $otDay = isset($hAtt->ot_map[$key]) ? $hAtt->ot_map[$key] : null;
                }

                if (($hours === null || $hours === 0) && ($otDay === null || $otDay == 0) && $key !== 'sun') {
                    $sheet->setCellValue($cell, '-');
                } else {
                    $display = (float)($hours ?? 0);
                    if ($otDay && (float)$otDay > 0) {
                        $display = $display - $otDay . ' + ' . ((float)$otDay);
                    }
                    if($key === 'sun' && $display <= 0) {
                        $sheet->setCellValue($cell, 'CLOSED');
                    $sheet->getStyle($cell)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('0c4d90'); // dark sky blue
                    $sheet->getStyle($cell)->getFont()
                          ->setBold(true)
                          ->getColor()->setARGB(Color::COLOR_WHITE);
                    $sheet->getStyle($cell)->getAlignment()
                          ->setHorizontal('center')
                          ->setVertical('center');
                    } else {
                        $sheet->setCellValue($cell, $display);
                    }
                }

                $sheet->getStyle($cell)->getAlignment()
                          ->setHorizontal('center')
                          ->setVertical('center');
            } else {
                if ($key === 'sun' && $val === 0 ) {
                    $sheet->setCellValue($cell, 'CLOSED');
                    $sheet->getStyle($cell)->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()->setARGB('0c4d90'); // dark sky blue
                    $sheet->getStyle($cell)->getFont()
                          ->setBold(true)
                          ->getColor()->setARGB(Color::COLOR_WHITE);
                    $sheet->getStyle($cell)->getAlignment()
                          ->setHorizontal('center')
                          ->setVertical('center');
                } else {
                    $isIn = (bool)$val;
                    $display = $isIn ? 'IN' : 'OFF';

                    // if IN and there is a daily overtime amount for this day, append it
                    if ($isIn && $att && is_array($att->overtime_map) && isset($att->overtime_map[$key]) && (float)$att->overtime_map[$key] > 0) {
                        $display = $display . ' + ' . number_format((float)$att->overtime_map[$key], 2);
                    }

                    $sheet->getStyle($cell)->getFont()
                          ->setBold(true);
                    $sheet->getStyle($cell)->getAlignment()
                          ->setHorizontal('center')
                          ->setVertical('center');
                    $sheet->setCellValue($cell, $display);

                    if (! $isIn) {
                        $sheet->getStyle($cell)->getFont()
                            ->setBold(true)
                            ->getColor()->setARGB('DC2626'); // red
                        $sheet->getStyle($cell)->getAlignment()
                            ->setHorizontal('center')
                            ->setVertical('center');
                    }
                }
            }

            $col++;
        }

        // attendance + payment columns
        $val = fn($v) => ($v === null || $v === '') ? '-' : $v;

$sheet->setCellValue("K{$rowIndex}", $val($item->present_days));

// Total hours column (L): for hourly show "normal [+ OT]" string, for daily keep dash
if ($item->type === 'hourly') {
    $normal = $item->total_hours ?? 0;
    $ot = $item->overtime_hours ?? 0;
    if ($ot && $ot > 0) {
        $hoursLabel = $normal . " + " . $ot . " hr";
    } else {
        $hoursLabel = $normal . " hrs";
    }
    $sheet->setCellValue("L{$rowIndex}", $val($hoursLabel));
} else {
    $sheet->setCellValue("L{$rowIndex}", $val('-'));
}

// Overtime column (M): daily uses overtime_amount (currency/amount), hourly uses overtime_hours
if ($item->type === 'daily_rate') {
    $sheet->setCellValue("M{$rowIndex}", $val(number_format((float)($item->overtime_amount ?? 0), 2)));
} else {
    $sheet->setCellValue("M{$rowIndex}", $val($item->overtime_hours) * $item->employee->hourly_rate);
}

$sheet->setCellValue("N{$rowIndex}", $val($item->weekly_amount));
$sheet->setCellValue("O{$rowIndex}", $val($item->cash_amount));
$sheet->setCellValue("P{$rowIndex}", $val($item->bank_amount));

// center align
$sheet->getStyle("K{$rowIndex}:P{$rowIndex}")
    ->getAlignment()
    ->setHorizontal('center')
    ->setVertical('center');


        $rowIndex++;
    }

    // auto width
    foreach (range('A', 'P') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // thin borders around everything used
    $lastRow = $rowIndex - 1;
    $sheet->getStyle("A1:P{$lastRow}")
        ->getBorders()
        ->getAllBorders()
        ->setBorderStyle(Border::BORDER_THIN);

    $fileName = "payroll_week_{$week}_{$year}.xlsx";
    $writer   = new Xlsx($spreadsheet);

    return response()->streamDownload(function () use ($writer) {
        $writer->save('php://output');
    }, $fileName, [
        'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    ]);
}


    /**
     * Optional extra: pure weekly attendance report screen (view only)
     * using IN / OFF like we did earlier
     */
    public function weeklyReport(Request $request)
{
    $year = (int) $request->input('year', now()->year);
    $week = (int) $request->input('week', now()->weekOfYear);
    $weeksInYear = Carbon::create($year, 12, 28)->isoWeek(); // to check 53 weeks

    // Fetch both daily_rate and hourly employees
    $employees = Employee::whereIn('type', ['daily_rate', 'hourly']) // Fetch both daily_rate and hourly employees
        ->where('is_active', true)
        ->orderBy('name')
        ->get();

    // Fetch attendance data for the specified year and week
    $attendance = DailyRateAttendance::where('year', $year)
        ->where('week_number', $week)
        ->get()
        ->keyBy('employee_id');

    // Include any existing payroll items for this week (to show weekly/addon/payment state)
    $run = PayrollRun::where('year', $year)
        ->where('week_number', $week)
        ->first();

    $itemsByEmployee = collect();
    if ($run) {
        $items = PayrollItem::where('payroll_run_id', $run->id)->get();
        $itemsByEmployee = $items->keyBy('employee_id');
    }

    return view('payroll.weekly_report', compact('year', 'week', 'employees', 'attendance', 'itemsByEmployee', 'weeksInYear'));
}


    public function weeklyReportCsv(Request $request): StreamedResponse
    {
        $year = (int) $request->input('year', now()->year);
        $week = (int) $request->input('week', now()->weekOfYear);

        // MISSING-10: Include both daily and hourly employees
        $employees = Employee::orderBy('name')->get();

        $dailyAttendance = DailyRateAttendance::where('year', $year)
            ->where('week_number', $week)
            ->get()
            ->keyBy('employee_id');

        $hourlyAttendance = HourlyAttendance::where('year', $year)
            ->where('week_number', $week)
            ->get()
            ->keyBy('employee_id');

        $filename = "weekly_attendance_{$year}_week_{$week}.csv";

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        $callback = function () use ($employees, $dailyAttendance, $hourlyAttendance, $year, $week, $dayKeys) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Employee Code',
                'Name',
                'Department',
                'Type',
                'Year',
                'Week',
                'Mon',
                'Tue',
                'Wed',
                'Thu',
                'Fri',
                'Sat',
                'Sun',
                'Present',
                'Absent / Off',
            ]);

            foreach ($employees as $employee) {
                $row = [
                    $employee->employee_code,
                    $employee->name,
                    $employee->department,
                    $employee->type === 'daily_rate' ? 'Daily' : 'Hourly',
                    $year,
                    $week,
                ];

                if ($employee->type === 'daily_rate') {
                    $att = $dailyAttendance[$employee->id] ?? null;
                    $daysMap = [
                        'mon' => 1, 'tue' => 1, 'wed' => 1,
                        'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0,
                    ];
                    if ($att && is_array($att->days_map)) {
                        $daysMap = array_merge($daysMap, $att->days_map);
                    }
                    $presentDays = $att->present_days ?? collect($daysMap)->only(['mon','tue','wed','thu','fri','sat'])->sum();
                    $absentDays  = max(0, 6 - $presentDays);

                    foreach ($dayKeys as $key) {
                        $val = $daysMap[$key] ?? 0;
                        $row[] = $key === 'sun' ? ($val ? 'SUN' : 'OFF') : ($val ? 'IN' : 'OFF');
                    }
                    $row[] = $presentDays;
                    $row[] = $absentDays;
                } else {
                    // Hourly: show hours worked per day
                    $att = $hourlyAttendance[$employee->id] ?? null;
                    $hoursMap = $att && is_array($att->hours_map) ? $att->hours_map : [];
                    $totalHours  = 0;
                    $daysPresent = 0;

                    foreach ($dayKeys as $key) {
                        $hrs = (float) ($hoursMap[$key] ?? 0);
                        $row[] = $hrs > 0 ? number_format($hrs, 2) . 'h' : 'OFF';
                        $totalHours += $hrs;
                        if ($hrs > 0) $daysPresent++;
                    }
                    $row[] = "{$daysPresent} days / " . number_format($totalHours, 2) . 'h';
                    $row[] = max(0, 7 - $daysPresent);
                }

                fputcsv($handle, $row);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }


    /**
     * Write one rich audit entry for a save or recalculate operation.
     * Stores per-employee before/after diffs so the history panel can render them.
     */
    private function writePayrollAudit(int $runId, string $actionType, array $diffs): void
    {
        try {
            AuditLog::create([
                'user_id'    => auth()->id(),
                'action'     => 'updated',
                'model_type' => 'PayrollRun',
                'model_id'   => $runId,
                'old_values' => ['action_type' => $actionType],
                'new_values' => ['action_type' => $actionType, 'employees' => $diffs],
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            logger()->error('Payroll audit write failed: ' . $e->getMessage());
        }
    }

}