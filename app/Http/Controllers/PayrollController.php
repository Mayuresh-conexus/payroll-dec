<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use App\Models\PayrollRun;
use App\Models\PayrollItem;
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
    $year = (int) $request->input('year', now()->year);
    $week = (int) $request->input('week', now()->weekOfYear);

    // 1. Check lock status per type for this week
    $dailyLockedWeek = DailyRateAttendance::where('year', $year)
        ->where('week_number', $week)
        ->where('locked', true)
        ->exists();

    $hourlyLockedWeek = HourlyAttendance::where('year', $year)
        ->where('week_number', $week)
        ->where('locked', true)
        ->exists();

    // 2. Load any existing payroll run
    $run = PayrollRun::with('items.employee')
        ->where('year', $year)
        ->where('week_number', $week)
        ->first();

    // 3. Always start from attendance based rows (fresh gross etc)
    $rows = $this->buildRowsFromAttendance($year, $week);

    // Index current rows by employee + type
    $rowsByKey = $rows->keyBy(function (array $row) {
        $emp = $row['employee'];
        return ($emp ? $emp->id : 'emp0') . '|' . $row['type'];
    });

    if ($run) {
        foreach ($run->items as $item) {
            $employee = $item->employee;
            if (! $employee) {
                continue;
            }

            $key = $employee->id . '|' . $item->type;

            $isDaily  = $item->type === 'daily_rate';
            $isHourly = $item->type === 'hourly';

            $usePayrollOnlyForThisType =
                ($isDaily && $dailyLockedWeek) ||
                ($isHourly && $hourlyLockedWeek);

            if ($usePayrollOnlyForThisType) {
                // 4a. Attendance locked: trust payroll completely for this type
                $gross = (float) ($item->gross_amount ?? 0);
                $cash  = (float) ($item->cash_amount ?? 0);

                if ($cash < 0) {
                    $cash = 0;
                }
                if ($cash > $gross) {
                    $cash = $gross;
                }

                // when payroll run is trusted for this type, map overtime field appropriately
                if ($isDaily) {
                    $overtimeField = 'overtime_amount';
                } else {
                    $overtimeField = 'overtime_hours';
                }

                $rowsByKey[$key] = [
                    'employee'        => $employee,
                    'type'            => $item->type,
                    'total_days'      => $item->total_days,
                    'present_days'    => $item->present_days,
                    'total_hours'     => $item->total_hours,
                    // map payroll item's overtime to the appropriate row key
                    $overtimeField   => $item->overtime_hours,
                    // preserve sunday hours from attendance row when available
                    'sun_hours'       => $rowsByKey[$key]['sun_hours'] ?? 0,
                    'gross_amount'    => $gross,
                    'cash_amount'     => $cash,
                    'bank_amount'     => $gross - $cash,
                ];
            } else {
                // 4b. Attendance not locked: use fresh attendance row, but cash from payroll if present
                if ($rowsByKey->has($key)) {
                    $row   = $rowsByKey->get($key);
                    $gross = (float) ($row['gross_amount'] ?? 0);
                    $cash  = (float) ($item->cash_amount ?? 0);

                    if ($cash < 0) {
                        $cash = 0;
                    }
                    if ($cash > $gross) {
                        $cash = $gross;
                    }

                    $row['cash_amount'] = $cash;
                    $row['bank_amount'] = $gross - $cash;

                    $rowsByKey[$key] = $row;
                } else {
                    // Safety: payroll row exists but attendance row missing
                    $gross = (float) ($item->gross_amount ?? 0);
                    $cash  = (float) ($item->cash_amount ?? 0);

                    if ($cash < 0) {
                        $cash = 0;
                    }
                    if ($cash > $gross) {
                        $cash = $gross;
                    }

                    // ensure missing attendance row maps overtime into the reasonable key
                    if ($item->type === 'daily_rate') {
                        $overtimeKey = 'overtime_amount';
                    } else {
                        $overtimeKey = 'overtime_hours';
                    }

                    $rowsByKey[$key] = [
                        'employee'        => $employee,
                        'type'            => $item->type,
                        'total_days'      => $item->total_days,
                        'present_days'    => $item->present_days,
                        'total_hours'     => $item->total_hours,
                        $overtimeKey     => $item->overtime_hours,
                        'sun_hours'       => 0,
                        'gross_amount'    => $gross,
                        'cash_amount'     => $cash,
                        'bank_amount'     => $gross - $cash,
                    ];
                }
            }
        }
    } else {
        // 5. No run at all: bank = gross - cash (cash is 0 by default)
        $rowsByKey = $rowsByKey->map(function (array $row) {
            $gross = (float) ($row['gross_amount'] ?? 0);
            $cash  = (float) ($row['cash_amount'] ?? 0);

            if ($cash < 0) {
                $cash = 0;
            }
            if ($cash > $gross) {
                $cash = $gross;
            }

            $row['cash_amount'] = $cash;
            $row['bank_amount'] = $gross - $cash;

            return $row;
        });
    }

    // Final rows collection
    $rows = $rowsByKey->values();

    $totals = [
        'gross' => $rows->sum('gross_amount'),
        'cash'  => $rows->sum('cash_amount'),
        'bank'  => $rows->sum('bank_amount'),
    ];

    return view('payroll.index', [
        'year'   => $year,
        'week'   => $week,
        'run'    => $run,
        'rows'   => $rows,
        'totals' => $totals,
    ]);
}


    protected function buildRowsFromAttendance(int $year, int $week)
    {
        $dailyAtt = DailyRateAttendance::with('employee')
            ->where('year', $year)
            ->where('week_number', $week)
            ->get();

        $hourlyAtt = HourlyAttendance::with('employee')
            ->where('year', $year)
            ->where('week_number', $week)
            ->get();

        $rows = collect();

        foreach ($dailyAtt as $att) {
            $employee = $att->employee;
            if (! $employee) {
                continue;
            }

            $totalDays   = $att->total_working_days ?? 6;
            $presentDays = $att->present_days ?? 0;
            $dailyRate     = $employee->daily_rate ?? 0;
            $overtimeAmount = $att->overtime_amount ?? 0;

            // include overtime amount in gross for daily-rate employees
            $gross = ($presentDays * $dailyRate) + $overtimeAmount;

            $rows->push([
                'employee'        => $employee,
                'type'            => 'daily_rate',
                'total_days'      => $totalDays,
                'present_days'    => $presentDays,
                // whether sunday was marked/present in this attendance week
                'sun_present'     => (!empty($att->days_map) && isset($att->days_map['sun']) && (int)$att->days_map['sun'] === 1),
                'total_hours'     => null,
                'sun_hours'       => null,
                // daily attendance stores overtime as an amount
                'overtime_amount' => $overtimeAmount,
                'gross_amount'    => $gross,
                'cash_amount'     => 0,
                'bank_amount'     => $gross,
            ]);
        }

        foreach ($hourlyAtt as $att) {
            $employee = $att->employee;
            if (! $employee) {
                continue;
            }

            $hours = $att->total_hours ?? 0;
            $ot    = $att->overtime_hours ?? 0;
            $rate  = $employee->hourly_rate ?? 0;

            $normalPay = $hours * $rate;
            $otPay     = $ot * $rate * 1;  // adjust factor if you want
            $gross     = $normalPay + $otPay;

            $rows->push([
                'employee'        => $employee,
                'type'            => 'hourly',
                'total_days'      => null,
                'present_days'    => null,
                // whether sunday had hours recorded
                'sun_present'     => (!empty($att->hours_map) && isset($att->hours_map['sun']) && (float)$att->hours_map['sun'] > 0),
                // actual hours recorded for Sunday (0 when none)
                'sun_hours'       => (!empty($att->hours_map) && isset($att->hours_map['sun']) ? (float)$att->hours_map['sun'] : 0),
                'total_hours'     => $hours,
                'overtime_hours'  => $ot,
                'gross_amount'    => $gross,
                'cash_amount'     => 0,
                'bank_amount'     => $gross,
            ]);
        }

        return $rows;
    }

    public function saveWeek(Request $request)
    {
        $data = $request->validate([
            'year'                 => 'required|integer',
            'week'                 => 'required|integer|min:1|max:52',
            'items'                => 'required|array',
            'items.*.employee_id'  => 'required|integer|exists:employees,id',
            'items.*.type'         => 'required|in:daily_rate,hourly',
            'items.*.total_days'   => 'nullable|integer',
            'items.*.present_days' => 'nullable|integer',
            'items.*.total_hours'  => 'nullable|numeric',
            'items.*.overtime'     => 'nullable|numeric',
            'items.*.gross'        => 'required|numeric',
            'items.*.cash'         => 'nullable|numeric',
            'items.*.bank'         => 'nullable|numeric',
        ]);

        $run = PayrollRun::updateOrCreate(
            ['year' => $data['year'], 'week_number' => $data['week']],
            [
                'status'       => 'draft',
                'created_by'   => auth()->id(),
                'generated_at' => now(),
            ]
        );

        foreach ($data['items'] as $row) {
            $gross = $row['gross'];
            $cash  = $row['cash'] ?? 0;
            $bank  = $row['bank'] ?? ($gross - $cash);

            // Save payroll item: map overtime into the correct column depending on employee type
            $payload = [
                'type'         => $row['type'],
                'total_days'   => $row['total_days'] ?? null,
                'present_days' => $row['present_days'] ?? null,
                'total_hours'  => $row['total_hours'] ?? null,
                'gross_amount' => $gross,
                'cash_amount'  => $cash,
                'bank_amount'  => $bank,
            ];

            if (($row['type'] ?? '') === 'daily_rate') {
                // daily rows: overtime is an amount
                $payload['overtime_amount'] = $row['overtime'] ?? 0;
                $payload['overtime_hours'] = null;
            } else {
                // hourly rows: overtime is hours
                $payload['overtime_hours'] = $row['overtime'] ?? 0;
                $payload['overtime_amount'] = null;
            }

            PayrollItem::updateOrCreate(
                [
                    'payroll_run_id' => $run->id,
                    'employee_id'    => $row['employee_id'],
                ],
                $payload
            );
        }

        return redirect()->route('payroll.index', [
            'year' => $data['year'],
            'week' => $data['week'],
        ])->with('success', 'Weekly payroll saved');
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
        'M3' => 'Overtime Hours',
        'N3' => 'Gross Salary',
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

                if (($hours === null || $hours === 0) && ($otDay === null || $otDay == 0)) {
                    $sheet->setCellValue($cell, '-');
                } else {
                    $display = (float)($hours ?? 0);
                    if ($otDay && (float)$otDay > 0) {
                        $display = $display - $otDay . ' + ' . ((float)$otDay);
                    }
                    $sheet->setCellValue($cell, $display);
                }

                $sheet->getStyle($cell)->getAlignment()
                          ->setHorizontal('center')
                          ->setVertical('center');
            } else {
                if ($key === 'sun') {
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
    $sheet->setCellValue("M{$rowIndex}", $val($item->overtime_hours));
}

$sheet->setCellValue("N{$rowIndex}", $val($item->gross_amount));
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

        $employees = Employee::where('type', 'daily_rate')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $attendance = DailyRateAttendance::where('year', $year)
            ->where('week_number', $week)
            ->get()
            ->keyBy('employee_id');

        return view('payroll.weekly_report', compact('year', 'week', 'employees', 'attendance'));
    }

    public function weeklyReportCsv(Request $request): StreamedResponse
    {
        $year = (int) $request->input('year', now()->year);
        $week = (int) $request->input('week', now()->weekOfYear);

        $employees = Employee::where('type', 'daily_rate')
            ->orderBy('name')
            ->get();

        $attendance = DailyRateAttendance::where('year', $year)
            ->where('week_number', $week)
            ->get()
            ->keyBy('employee_id');

        $filename = "weekly_attendance_{$year}_week_{$week}.csv";

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        $callback = function () use ($employees, $attendance, $year, $week, $dayKeys) {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, [
                'Employee Code',
                'Name',
                'Department',
                'Year',
                'Week',
                'Mon',
                'Tue',
                'Wed',
                'Thu',
                'Fri',
                'Sat',
                'Sun',
                'Present days',
                'Absent days',
            ]);

            foreach ($employees as $employee) {
                $att = $attendance[$employee->id] ?? null;

                $daysMap = [
                    'mon' => 1,
                    'tue' => 1,
                    'wed' => 1,
                    'thu' => 1,
                    'fri' => 1,
                    'sat' => 1,
                    'sun' => 0,
                ];

                if ($att && is_array($att->days_map)) {
                    $daysMap = array_merge($daysMap, $att->days_map);
                }

                $presentDays = $att->present_days ?? collect($daysMap)->only([
                    'mon', 'tue', 'wed', 'thu', 'fri', 'sat',
                ])->sum();

                $absentDays = max(0, 6 - $presentDays);

                $row = [
                    $employee->employee_code,
                    $employee->name,
                    $employee->department,
                    $year,
                    $week,
                ];

                foreach ($dayKeys as $key) {
                    $val = $daysMap[$key] ?? 0;
                    $row[] = $key === 'sun' ? 'OFF' : ($val ? 'IN' : 'OFF');
                }

                $row[] = $presentDays;
                $row[] = $absentDays;

                fputcsv($handle, $row);
            }

            fclose($handle);
        };

        return response()->streamDownload($callback, $filename, $headers);
    }
}