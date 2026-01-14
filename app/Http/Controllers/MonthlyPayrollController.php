<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\Employee; 
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

class MonthlyPayrollController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));

        $rows = $this->buildMonthRows($month);

        $totals = [
            'gross' => $rows->sum('gross_amount'),
            'cash'  => $rows->sum('cash_amount'),
            'bank'  => $rows->sum('bank_amount'),
        ];

        return view('payroll.monthly_index', compact('month', 'rows', 'totals'));
    }

    protected function getWeeksForMonth(string $month): array
    {
        $mStart = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
        $mEnd   = $mStart->copy()->endOfMonth();

        $runs = PayrollRun::query()
            ->where('period_type', 'weekly')
            ->whereNotNull('week_number')
            ->where('week_number', '>', 0)
            ->where('year', (int) $mStart->year)
            ->get(['year', 'week_number']);

        $weeks = [];

        foreach ($runs as $r) {
            $weekStart = Carbon::create()->setISODate((int)$r->year, (int)$r->week_number)->startOfWeek(Carbon::MONDAY);
            $weekEnd   = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

            if ($weekStart->lte($mEnd) && $weekEnd->gte($mStart)) {
                $weeks[] = (int) $r->week_number;
            }
        }

        $weeks = array_values(array_unique($weeks));
        sort($weeks);

        return $weeks;
    }

   protected function buildEmployeeWeekMap(string $month): array
{
    $weeks = $this->getWeeksForMonth($month);
    if (empty($weeks)) return [];

    $mStart = Carbon::createFromFormat('Y-m-d', $month . '-01');
    $mEnd = $mStart->copy()->endOfMonth();

    $runIds = PayrollRun::query()
        ->where(function ($q) {
            $q->where('period_type', 'weekly')->orWhereNull('period_type');
        })
        ->where('year', (int) $mStart->year)
        ->whereIn('week_number', $weeks)
        ->pluck('id')
        ->all();

    if (empty($runIds)) return [];

    $items = PayrollItem::with('payrollRun')
        ->whereIn('payroll_run_id', $runIds)
        ->get();

    $map = [];

    foreach ($items as $it) {
        $run = $it->payrollRun;
        if (!$run) continue;

        $w = (int) ($run->week_number ?? 0);
        if ($w <= 0) continue;

        $k = $it->employee_id . '|' . $it->type;

        if (!isset($map[$k])) $map[$k] = [];
        if (!isset($map[$k][$w])) {
            $map[$k][$w] = [
                'gross' => 0.0,
                'cash' => 0.0,
                'bank' => 0.0,
                'weekly' => 0.0,
                'addon_total' => 0.0,
                'addon_cash_total' => 0.0,
                'addons' => []
            ];
        }

        $weekStart = Carbon::create()->setISODate((int)$run->year, (int)$run->week_number, 1)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

        $dayKeys = ['mon','tue','wed','thu','fri','sat','sun'];

        // Collect attendance data
        $presentMap = null;
        if ($it->type === 'daily_rate') {
            $att = DailyRateAttendance::where('employee_id', $it->employee_id)
                ->where('year', (int)$run->year)
                ->where('week_number', (int)$run->week_number)
                ->first();
            if ($att) {
                $dm = $att->days_map ?? null;
                if (is_string($dm)) $dm = json_decode($dm, true);
                if (is_array($dm)) {
                    $presentMap = [];
                    foreach ($dayKeys as $d) {
                        $presentMap[$d] = !empty($dm[$d]) ? 1 : 0;
                    }
                }
            }
        } else {
            $att = HourlyAttendance::where('employee_id', $it->employee_id)
                ->where('year', (int)$run->year)
                ->where('week_number', (int)$run->week_number)
                ->first();
            if ($att) {
                $hm = $att->hours_map ?? null;
                if (is_string($hm)) $hm = json_decode($hm, true);
                if (is_array($hm)) {
                    $presentMap = [];
                    foreach ($dayKeys as $d) {
                        $presentMap[$d] = (!empty($hm[$d]) && (float)$hm[$d] > 0) ? 1 : 0;
                    }
                }
            }
        }

        if ($presentMap === null) {
            if (!empty($it->days_map)) {
                $dm = $it->days_map;
                if (is_string($dm)) $dm = json_decode($dm, true);
                if (is_array($dm)) {
                    $presentMap = [];
                    foreach ($dayKeys as $d) {
                        $presentMap[$d] = !empty($dm[$d]) ? 1 : 0;
                    }
                }
            }
        }

        // Calculate present days
        $totalPresent = 0;
        $presentInMonth = 0;
        for ($i = 0; $i < 7; $i++) {
            $date = $weekStart->copy()->addDays($i);
            $key = $dayKeys[$i];
            $isPresent = null;
            if (is_array($presentMap) && array_key_exists($key, $presentMap)) {
                $isPresent = (int) $presentMap[$key];
            }

            if ($isPresent === null) {
                $isPresent = null;
            }

            if ($isPresent === 1) {
                $totalPresent++;
                if ($date->month === $mStart->month) {
                    $presentInMonth++;
                }
            }
        }

        if ($totalPresent > 0) {
            $factor = $presentInMonth / max(1, $totalPresent);
            if ($presentInMonth <= 0) {
                continue;
            }

            $itWeekly = (float) ($it->weekly_amount ?? 0);
            $addons = $it->addons ?? null;
            if (is_string($addons)) $addons = json_decode($addons, true);

            $addonInMonth = 0.0;
            $addonCashInMonth = 0.0;
            $addonsInMonthList = [];

            if (is_array($addons)) {
                foreach ($addons as $ad) {
                    $adDate = null;
                    if (!empty($ad['date'])) {
                        try {
                            $adDate = Carbon::parse($ad['date']);
                        } catch (\Throwable $e) {
                            $adDate = null;
                        }
                    }
                    if (!$adDate) continue;
                    if ($adDate->format('Y-m') !== $mStart->format('Y-m')) continue;

                    $amt = (float)($ad['amount'] ?? 0);
                    if ($amt <= 0) continue;
                    $cashFlag = !empty($ad['cash']);
                    $addonInMonth += $amt;
                    if ($cashFlag) $addonCashInMonth += $amt;

                    $addonsInMonthList[] = [
                        'date' => $adDate->toDateString(),
                        'amount' => $amt,
                        'cash' => $cashFlag,
                    ];
                }
            }

            $proratedWeekly = $itWeekly * $factor;
            $proratedOvertime = (float) ($it->overtime_amount ?? 0) * $factor;
            $proratedCash = (float) ($it->cash_amount ?? 0) * $factor;
            $proratedBank = (float) ($it->bank_amount ?? 0) * $factor;

            $grossToAdd = $proratedWeekly + $addonInMonth + $proratedOvertime;
            $cashToAdd = $proratedCash + $addonCashInMonth;
            $bankToAdd = max(0, $grossToAdd - $cashToAdd);

            // Prorate the cash for each week if the week is partial within the month
            $map[$k][$w]['gross'] += $grossToAdd;
            $map[$k][$w]['cash'] += $cashToAdd;
            $map[$k][$w]['bank'] += $bankToAdd;

            $map[$k][$w]['weekly'] += $proratedWeekly;
            $map[$k][$w]['addon_total'] += $addonInMonth;
            $map[$k][$w]['addon_cash_total'] += $addonCashInMonth;

            $map[$k][$w]['addons'] = array_merge($map[$k][$w]['addons'], $addonsInMonthList);
        }
    }

    return $map;
}


protected function buildMonthRows(string $month)
{
    $start = Carbon::parse($month . '-01')->startOfMonth();
    $end = $start->copy()->endOfMonth();

    $monthWeeks = $this->getWeeksForMonth($month);
    $weekMap = $this->buildEmployeeWeekMap($month);

    $rows = collect();

    foreach ($weekMap as $key => $weekData) {
        list($empId, $type) = explode('|', $key);
        $emp = Employee::find($empId);
        if (!$emp) continue;

        $weeklyAmount = 0.0;
        $addonsTotal = 0.0;
        $addonsCashTotal = 0.0;
        $addonsList = [];
        $cashAmount = 0.0;
        $grossAmount = 0.0;
        $bankAmount = 0.0;

        foreach ($weekData as $weekNumber => $data) {
            // Accumulate weekly amount and addons
            $weeklyAmount += $data['weekly'] ?? 0;
            $addonsTotal += $data['addon_total'] ?? 0;
            $addonsCashTotal += $data['addon_cash_total'] ?? 0;
            $addonsList = array_merge($addonsList, $data['addons'] ?? []);

            // Get the payroll item for the employee and the corresponding week
            $payrollItem = PayrollItem::where('employee_id', $empId)
                ->whereHas('payrollRun', function ($query) use ($month, $weekNumber) {
                    $query->where('year', (int) $month)
                          ->where('week_number', $weekNumber);
                })
                ->first();

            if ($payrollItem) {
                // Log payroll item found
                \Log::debug("Payroll Item Found for Employee {$emp->employee_code} in Week {$weekNumber}: Cash Amount = " . $payrollItem->cash_amount);

                // Get Attendance Data
                $attendanceData = null;
                if ($payrollItem->type === 'daily_rate') {
                    $attendanceData = DailyRateAttendance::where('employee_id', $empId)
                        ->where('year', $start->year)
                        ->where('week_number', $weekNumber)
                        ->first();
                } else {
                    $attendanceData = HourlyAttendance::where('employee_id', $empId)
                        ->where('year', $start->year)
                        ->where('week_number', $weekNumber)
                        ->first();
                }

                // Log the attendance data
                \Log::debug("Attendance Data for Employee {$emp->employee_code} in Week {$weekNumber}: " . json_encode($attendanceData));

                if ($attendanceData) {
                    // Get the present map (array of present days)
                    $presentMap = ($payrollItem->type === 'daily_rate') ? $attendanceData->days_map : $attendanceData->hours_map;

                    // Log the present map to debug
                    \Log::debug("Present Map for Employee {$emp->employee_code} in Week {$weekNumber}: " . json_encode($presentMap));

                    // If present map is in correct format (array), proceed with calculation
                    if (is_array($presentMap)) {
                        // Calculate total present days in the week (count non-zero entries in the present map)
                        $totalPresentDays = count(array_filter($presentMap));
                        \Log::debug("Total Present Days in Week {$weekNumber}: {$totalPresentDays}");

                        // Count present days in the current month (November or December)
                        $presentInCurrentMonth = 0;

                        $weekStart = Carbon::parse("{$start->year}-W{$weekNumber}-1");
                        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

                        for ($i = 0; $i < 7; $i++) {
                            $date = $weekStart->copy()->addDays($i);
                            $key = $dayKeys[$i];
                            if (isset($presentMap[$key]) && $presentMap[$key] > 0 && $date->month == $start->month) {
                                $presentInCurrentMonth++;
                            }
                        }

                        // Prorate the cash for the current month
                        if ($presentInCurrentMonth > 0 && $totalPresentDays > 0) {
                            $proratedCash = ($payrollItem->cash_amount / $totalPresentDays) * $presentInCurrentMonth;
                            $cashAmount += $proratedCash;
                        }
                    }
                }

                // Add the addon cash to the total cash
                if ($addonsCashTotal > 0) {
                    $cashAmount += $addonsCashTotal;
                    \Log::debug("Adding Addon Cash for Employee {$emp->employee_code}: {$addonsCashTotal}");
                }

                // Add to gross amount and bank amount
                $grossAmount += $payrollItem->gross_amount ?? 0;
                $bankAmount += $payrollItem->bank_amount ?? 0;
            }
        }

        // Final Calculation
        $grossAmount = $weeklyAmount + $addonsTotal; // Sum weekly and addons for gross amount
        $bankAmount = $grossAmount - $cashAmount; // Remaining balance for bank

        \Log::debug("Final Calculation for Employee {$emp->employee_code}: Gross Amount = {$grossAmount}, Cash Amount = {$cashAmount}, Bank Amount = {$bankAmount}");

        $rows->push([
            'employee' => $emp,
            'type' => $type,
            'gross_amount' => $grossAmount,
            'cash_amount' => $cashAmount,
            'bank_amount' => $bankAmount,
            'weekly_amount' => $weeklyAmount,
            'addons' => $addonsList,
            'addons_total' => $addonsTotal,
            'addons_cash_total' => $addonsCashTotal,
        ]);
    }

    return $rows;
}



    public function saveMonth(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|date_format:Y-m',
            'items' => 'required|array',
            'items.*.employee_id' => 'required|integer|exists:employees,id',
            'items.*.type' => 'required|in:daily_rate,hourly',
            'items.*.gross' => 'required|numeric',
            'items.*.cash' => 'nullable|numeric',
            'items.*.bank' => 'nullable|numeric',
            'items.*.weekly_amount' => 'nullable|numeric',
            'items.*.addons' => 'nullable|array',
            'items.*.addons.*.date' => 'nullable|date',
            'items.*.addons.*.amount' => 'nullable|numeric',
            'items.*.addons.*.cash' => 'nullable|boolean',
            'items.*.overtime' => 'nullable|numeric',
        ]);

        $month = $data['month'];
        $monthFormatted = str_replace('-', '', $month);
        $year = (int) substr($month, 0, 4);

        $run = PayrollRun::updateOrCreate(
            ['period_type' => 'monthly', 'month' => $month],
            [
                'year' => $year,
                'week_number' => 0 . $monthFormatted,
                'status' => 'draft',
                'created_by' => auth()->id(),
                'generated_at' => now(),
                'period_type' => 'monthly',
                'month' => $month,
            ]
        );

        foreach ($data['items'] as $row) {
            $gross = $row['gross'];
            $cash = $row['cash'] ?? 0;
            $bank = $row['bank'] ?? ($gross - $cash);

            $weeklyAmountOrig = (float) ($row['weekly_amount'] ?? 0);

            $addonsOrig = $row['addons'] ?? [];
            $updatedAddons = [];
            $totalAddonAmount = 0;
            $totalAddonCash = 0;

            foreach ($addonsOrig as $ad) {
                $date = $ad['date'] ?? null;
                $amt = (float) ($ad['amount'] ?? 0);
                $cashFlag = isset($ad['cash']) ? (bool) $ad['cash'] : false;

                if ($amt <= 0) continue;

                $updatedAddons[] = ['date' => $date, 'amount' => $amt, 'cash' => $cashFlag];
                $totalAddonAmount += $amt;
                if ($cashFlag) {
                    $totalAddonCash += $amt;
                }
            }

            $totalCash = $weeklyAmountOrig + $totalAddonCash;
            $grossTotal = $weeklyAmountOrig + $totalAddonAmount;
            $bank = max(0, $grossTotal - $totalCash);

            $payload = [
                'payroll_run_id' => $run->id,
                'employee_id' => $row['employee_id'],
                'type' => $row['type'],
                'gross_amount' => $grossTotal,
                'cash_amount' => $totalCash,
                'bank_amount' => $bank,
                'weekly_amount' => $weeklyAmountOrig,
                'addons' => $updatedAddons,
            ];

            PayrollItem::updateOrCreate(
                [
                    'payroll_run_id' => $run->id,
                    'employee_id' => $row['employee_id'],
                ],
                $payload
            );
        }

        return redirect()->route('payroll.monthly.index', ['month' => $month])
            ->with('success', 'Monthly payroll saved');
    }

    // Export to XLSX (monthly)
    public function exportMonthXlsx(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $rows = $this->buildMonthRows($month);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monthly Payroll');

        // Palette
        $navy = '0F172A';
        $slate = '334155';
        $lighter = 'F8FAFC';
        $white = 'FFFFFF';
        $border = 'E2E8F0';

        // Headers (fixed, using Weeks column)
        $headers = [
            'Employee Code',
            'Employee Name',
            'Type',
            'Weeks',
            'Gross',
            'Cash',
            'Bank',
            'Note',
            'Transfer ID',
            'Transfer Date',
            'Transfer Status',
        ];
        $lastColLetter = Coordinate::stringFromColumnIndex(count($headers));

        // Title
        $sheet->setCellValue('A1', 'MONTHLY PAYROLL  ' . $month);
        $sheet->mergeCells("A1:{$lastColLetter}1");
        $sheet->getRowDimension(1)->setRowHeight(26);
        $sheet->getStyle("A1:{$lastColLetter}1")->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => $white]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $navy]],
        ]);

        // Header row (row 3)
        $rowHeader = 3;
        foreach ($headers as $i => $h) {
            $this->setCell($sheet, $i + 1, $rowHeader, $h);
        }

        $sheet->getRowDimension(3)->setRowHeight(20);
        $sheet->getStyle("A3:{$lastColLetter}3")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $white]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => $slate]],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $border]],
            ],
        ]);

        $sheet->freezePane('A4');
        $sheet->setAutoFilter("A3:{$lastColLetter}3");

        // Widths
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(30);
        $sheet->getStyle('H:H')->getAlignment()->setWrapText(true);

        // Data
        $rowIndex = 4;
        foreach ($rows as $r) {
            $emp = $r['employee'];

            $this->setCell($sheet, 1, $rowIndex, $emp->employee_code);
            $this->setCell($sheet, 2, $rowIndex, $emp->name);
            $this->setCell($sheet, 3, $rowIndex, $r['type'] === 'daily_rate' ? 'Daily' : 'Hourly');
            $this->setCell($sheet, 4, $rowIndex, $r['weeks_display'] ?? '');

            $this->setCell($sheet, 5, $rowIndex, (float) ($r['gross_amount'] ?? 0));
            $this->setCell($sheet, 6, $rowIndex, (float) ($r['cash_amount'] ?? 0));
            $this->setCell($sheet, 7, $rowIndex, (float) ($r['bank_amount'] ?? 0));

            $this->setCell($sheet, 8, $rowIndex, $r['note'] ?? '');
            $this->setCell($sheet, 9, $rowIndex, $r['transfer_id'] ?? '');

            // Transfer date in column 10 (J)
            if (!empty($r['transfer_date'])) {
                try {
                    $dt = Carbon::parse($r['transfer_date']);
                    $this->setCell($sheet, 10, $rowIndex, ExcelDate::PHPToExcel($dt->toDateTime()));
                    $sheet->getStyle("J{$rowIndex}")->getNumberFormat()->setFormatCode('yyyy-mm-dd');
                } catch (\Throwable $e) {
                    $this->setCell($sheet, 10, $rowIndex, (string) $r['transfer_date']);
                }
            } else {
                $this->setCell($sheet, 10, $rowIndex, '');
            }

            $this->setCell($sheet, 11, $rowIndex, $r['transfer_status'] ?? '');

            $rowIndex++;
        }

        $lastRow = max(4, $rowIndex - 1);
        $dataRange = "A4:{$lastColLetter}{$lastRow}";

        $sheet->getStyle($dataRange)->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $border]],
            ],
        ]);

        // Zebra
        for ($r = 4; $r <= $lastRow; $r++) {
            if (($r % 2) === 0) {
                $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($lighter);
            }
        }

        // Money formats (E, F, G)
        $sheet->getStyle("E4:G{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        $sheet->getStyle("E4:G{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Conditional formatting for status (K)
        $statusRange = "K4:K{$lastRow}";

        $condPending = new Conditional();
        $condPending->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
        $condPending->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
        $condPending->setText('pending');
        $condPending->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
        $condPending->getStyle()->getFont()->getColor()->setRGB('92400E');

        $condCompleted = new Conditional();
        $condCompleted->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
        $condCompleted->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
        $condCompleted->setText('completed');
        $condCompleted->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCFCE7');
        $condCompleted->getStyle()->getFont()->getColor()->setRGB('166534');

        $condFailed = new Conditional();
        $condFailed->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
        $condFailed->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
        $condFailed->setText('failed');
        $condFailed->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
        $condFailed->getStyle()->getFont()->getColor()->setRGB('991B1B');

        $sheet->getStyle($statusRange)->setConditionalStyles([$condPending, $condCompleted, $condFailed]);

        // Autosize
        foreach (range('A', 'K') as $col) {
            if (in_array($col, ['B', 'D', 'H'], true)) continue;
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        $fileName = "payroll_month_{$month}.xlsx";
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
