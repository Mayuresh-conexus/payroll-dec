<?php

namespace App\Http\Controllers;

use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\HourlyAttendance;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class MonthlyPayrollController extends Controller
{
    public function index(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));

        $rows = $this->buildMonthRows($month);

        $totals = [
            'gross' => $rows->sum('gross_amount'),
            'cash' => $rows->sum('cash_amount'),
            'bank' => $rows->sum('bank_amount'),
        ];

        return view('payroll.monthly_index', compact('month', 'rows', 'totals'));
    }

    protected function getWeeksForMonth(string $month): array
    {
        $mStart = Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
        $mEnd = $mStart->copy()->endOfMonth();

        // Include NULL period_type for backward-compat (runs saved before the period_type fix)
        $runs = PayrollRun::query()
            ->where(function ($q) {
                $q->where('period_type', 'weekly')->orWhereNull('period_type');
            })
            ->whereNotNull('week_number')
            ->where('week_number', '>', 0)
            ->whereIn('year', [(int) $mStart->year - 1, (int) $mStart->year, (int) $mStart->year + 1])
            ->get(['id', 'year', 'week_number']);

        $runIds = [];

        foreach ($runs as $r) {
            $weekStart = Carbon::create()->setISODate((int) $r->year, (int) $r->week_number)->startOfWeek(Carbon::MONDAY);
            $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

            if ($weekStart->lte($mEnd) && $weekEnd->gte($mStart)) {
                $runIds[] = (int) $r->id;
            }
        }

        return array_values(array_unique($runIds));
    }

    protected function buildEmployeeWeekMap(string $month): array
    {
        $runIds = $this->getWeeksForMonth($month);
        if (empty($runIds)) {
            return [];
        }

        $mStart = Carbon::createFromFormat('Y-m-d', $month.'-01');
        $mEnd = $mStart->copy()->endOfMonth();

        $items = PayrollItem::with('payrollRun')
            ->whereIn('payroll_run_id', $runIds)
            ->get();

        $map = [];

        foreach ($items as $it) {
            $run = $it->payrollRun;
            if (! $run) {
                continue;
            }

            $year = (int) ($run->year ?? 0);
            $w = (int) ($run->week_number ?? 0);
            if ($w <= 0 || $year <= 0) {
                continue;
            }

            $weekKey = sprintf('%04d-%02d', $year, $w);

            $k = $it->employee_id.'|'.$it->type;

            if (! isset($map[$k])) {
                $map[$k] = [];
            }
            if (! isset($map[$k][$weekKey])) {
                $map[$k][$weekKey] = [
                    'year' => $year,
                    'week' => $w,
                    'gross' => 0.0,
                    'cash' => 0.0,
                    'bank' => 0.0,
                    'weekly' => 0.0,
                    'addon_total' => 0.0,
                    'addon_cash_total' => 0.0,
                    'addons' => [],
                ];
            }

            $weekStart = Carbon::create()->setISODate($year, $w, 1)->startOfDay();
            $weekEnd = $weekStart->copy()->addDays(6)->endOfDay();

            $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

            // Collect attendance data
            $presentMap = null;
            if ($it->type === 'daily_rate') {
                $att = DailyRateAttendance::where('employee_id', $it->employee_id)
                    ->where('year', (int) $run->year)
                    ->where('week_number', (int) $run->week_number)
                    ->first();
                if ($att) {
                    $dm = $att->days_map ?? null;
                    if (is_string($dm)) {
                        $dm = json_decode($dm, true);
                    }
                    if (is_array($dm)) {
                        $presentMap = [];
                        foreach ($dayKeys as $d) {
                            $presentMap[$d] = ! empty($dm[$d]) ? 1 : 0;
                        }
                    }
                }
            } else {
                $att = HourlyAttendance::where('employee_id', $it->employee_id)
                    ->where('year', (int) $run->year)
                    ->where('week_number', (int) $run->week_number)
                    ->first();
                if ($att) {
                    $hm = $att->hours_map ?? null;
                    if (is_string($hm)) {
                        $hm = json_decode($hm, true);
                    }
                    if (is_array($hm)) {
                        $presentMap = [];
                        foreach ($dayKeys as $d) {
                            $presentMap[$d] = (! empty($hm[$d]) && (float) $hm[$d] > 0) ? 1 : 0;
                        }
                    }
                }
            }

            if ($presentMap === null) {
                if (! empty($it->days_map)) {
                    $dm = $it->days_map;
                    if (is_string($dm)) {
                        $dm = json_decode($dm, true);
                    }
                    if (is_array($dm)) {
                        $presentMap = [];
                        foreach ($dayKeys as $d) {
                            $presentMap[$d] = ! empty($dm[$d]) ? 1 : 0;
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
                if (is_string($addons)) {
                    $addons = json_decode($addons, true);
                }

                $addonInMonth = 0.0;
                $addonCashInMonth = 0.0;
                $addonsInMonthList = [];

                if (is_array($addons)) {
                    foreach ($addons as $ad) {
                        $adDate = null;
                        if (! empty($ad['date'])) {
                            try {
                                $adDate = Carbon::parse($ad['date']);
                            } catch (\Throwable $e) {
                                $adDate = null;
                            }
                        }
                        if (! $adDate) {
                            continue;
                        }
                        if ($adDate->format('Y-m') !== $mStart->format('Y-m')) {
                            continue;
                        }

                        $amt = (float) ($ad['amount'] ?? 0);
                        if ($amt <= 0) {
                            continue;
                        }
                        $cashFlag = ! empty($ad['cash']);
                        $addonInMonth += $amt;
                        if ($cashFlag) {
                            $addonCashInMonth += $amt;
                        }

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
                $map[$k][$weekKey]['gross'] += $grossToAdd;
                $map[$k][$weekKey]['cash'] += $cashToAdd;
                $map[$k][$weekKey]['bank'] += $bankToAdd;

                $map[$k][$weekKey]['weekly'] += $proratedWeekly;
                $map[$k][$weekKey]['addon_total'] += $addonInMonth;
                $map[$k][$weekKey]['addon_cash_total'] += $addonCashInMonth;

                $map[$k][$weekKey]['addons'] = array_merge($map[$k][$weekKey]['addons'], $addonsInMonthList);
            }
        }

        return $map;
    }

    protected function buildMonthRows(string $month)
    {
        $start = Carbon::parse($month.'-01')->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $firstMondayInMonth = $start->copy();
        while ((int) $firstMondayInMonth->dayOfWeek !== Carbon::MONDAY) {
            $firstMondayInMonth->addDay();
        }

        $weekMap = $this->buildEmployeeWeekMap($month);

        // Load saved monthly run + its items (if already saved)
        $monthlyRun = PayrollRun::where('period_type', 'monthly')
            ->where('month', $month)
            ->first();

        $savedMonthlyItems = collect();
        if ($monthlyRun) {
            $savedMonthlyItems = PayrollItem::where('payroll_run_id', $monthlyRun->id)->get()
                ->keyBy(function ($it) {
                    return $it->employee_id.'|'.$it->type;
                });
        }

        $rows = collect();

        foreach ($weekMap as $key => $weekData) {
            [$empId, $type] = explode('|', $key);
            $emp = Employee::find($empId);
            if (! $emp) {
                continue;
            }

            $weeklyAmount = 0.0;
            $addonsTotal = 0.0;
            $addonsCashTotal = 0.0;
            $addonsList = [];
            $cashAmount = 0.0;
            $bankFixTotal = 0.0; // sum of prorated bank_transfer_fix_amount across weeks in this month

            $carryOver = $this->getMonthAdvanceCarryOver((int) $empId, $month);
            $runningAdvanceBalance = $carryOver;

            ksort($weekData); // Ensure sequential chronological processing

            foreach ($weekData as $weekKey => $data) {
                $year = (int) ($data['year'] ?? $start->year);
                $weekNumber = (int) ($data['week'] ?? 0);
                if ($weekNumber <= 0) {
                    continue;
                }

                // weekly + addons already prorated by month in buildEmployeeWeekMap
                $weeklyAmount += (float) ($data['weekly'] ?? 0);
                $addonsTotal += (float) ($data['addon_total'] ?? 0);
                $addonsCashTotal += (float) ($data['addon_cash_total'] ?? 0);
                $addonsList = array_merge($addonsList, $data['addons'] ?? []);

                // Get weekly payroll item (weekly run) to pick cash_amount source
                $payrollItem = PayrollItem::where('employee_id', $empId)
                    ->where('type', $type)
                    ->whereHas('payrollRun', function ($q) use ($year, $weekNumber) {
                        $q->where('year', $year)
                            ->where('week_number', $weekNumber)
                            ->where(function ($qq) {
                                $qq->where('period_type', 'weekly')->orWhereNull('period_type');
                            });
                    })
                    ->first();

                if (! $payrollItem) {
                    continue;
                }

                // Compute advance_given the same way loadPrevAdvanceBalances does:
                // bank_amount - weekly_amount is the source of truth (advance_given field
                // may be 0 if the week was saved via the old AttendanceService path).
                $given = max(0.0, (float) ($payrollItem->bank_amount ?? 0) - (float) ($payrollItem->weekly_amount ?? 0));
                $recovered = (float) ($payrollItem->advance_recovered ?? 0);

                $weekStart = Carbon::create()->setISODate($year, $weekNumber, 1)->startOfDay();

                // Only count advances for weeks starting inside this month.
                if ($weekStart->gte($firstMondayInMonth)) {
                    $runningAdvanceBalance = max(0.0, $runningAdvanceBalance + $given - $recovered);
                }

                // Attendance map for this week
                $attendanceData = null;
                if ($type === 'daily_rate') {
                    $attendanceData = DailyRateAttendance::where('employee_id', $empId)
                        ->where('year', $year)
                        ->where('week_number', $weekNumber)
                        ->first();
                    $presentMap = $attendanceData?->days_map;
                } else {
                    $attendanceData = HourlyAttendance::where('employee_id', $empId)
                        ->where('year', $year)
                        ->where('week_number', $weekNumber)
                        ->first();
                    $presentMap = $attendanceData?->hours_map;
                }

                if (is_string($presentMap)) {
                    $presentMap = json_decode($presentMap, true);
                }
                if (! is_array($presentMap)) {
                    $presentMap = [];
                }

                // Total present days in week
                $totalPresentDays = 0;
                foreach ($presentMap as $v) {
                    if ((float) $v > 0) {
                        $totalPresentDays++;
                    }
                }
                if ($totalPresentDays <= 0) {
                    continue;
                }

                // Present days that fall inside current month only
                $presentInCurrentMonth = 0;
                $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

                for ($i = 0; $i < 7; $i++) {
                    $date = $weekStart->copy()->addDays($i);
                    $kDay = $dayKeys[$i];

                    $isPresent = ! empty($presentMap[$kDay]) && (float) $presentMap[$kDay] > 0;
                    if ($isPresent && $date->month === $start->month) {
                        $presentInCurrentMonth++;
                    }
                }

                if ($presentInCurrentMonth > 0) {
                    $factor = $presentInCurrentMonth / max(1, $totalPresentDays);
                    $cashAmount += (float) $payrollItem->cash_amount * $factor;
                    // Bank baseline: prorate the actual weekly bank amount to allow proper advance settlement
                    $bankFixTotal += (float) ($payrollItem->bank_amount ?? 0) * $factor;
                }
            }

            // Add addon cash that is marked cash=true (already month filtered in buildEmployeeWeekMap)
            $cashAmount += (float) $addonsCashTotal;

            $grossAmount = $weeklyAmount + $addonsTotal;

            // Advance balance for this month
            $advanceBeforeSettle = round($runningAdvanceBalance, 2);

            // Merge saved monthly fields if present
            $saved = $savedMonthlyItems->get($empId.'|'.$type);

            // Monthly settlement entered by admin (stored in monthly PayrollItem.advance_recovered)
            $advanceSettled = (float) ($saved?->advance_recovered ?? 0);
            $advanceBalance = round(max(0, $advanceBeforeSettle - $advanceSettled), 2);

            // Bank = prorated bank_fix baseline MINUS any monthly settlement.
            // Cash is NOT touched on monthly settlement (cash is already paid weekly).
            $bankAmount = round(max(0.0, $bankFixTotal - $advanceSettled), 2);

            $rows->push([
                'employee' => $emp,
                'type' => $type,
                'gross_amount' => $grossAmount,
                'cash_amount' => $cashAmount,
                'bank_amount' => $bankAmount,
                'bank_fix_total' => round($bankFixTotal, 2), // prorated bank_fix baseline (for JS)
                'weekly_amount' => $weeklyAmount,
                'addons' => $addonsList,
                'addons_total' => $addonsTotal,
                'addons_cash_total' => $addonsCashTotal,
                // advance tracking
                'advance_balance' => $advanceBalance,
                'advance_before_settle' => $advanceBeforeSettle,
                'advance_settled' => $advanceSettled,
                // transfer / note fields
                'transfer_id' => $saved?->transfer_id,
                'transfer_date' => $saved?->transfer_date,
                'transfer_status' => $saved?->transfer_status ?? 'pending',
                'note' => $saved?->note,
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
            'items.*.bank_fix_total' => 'nullable|numeric|min:0',
            'items.*.overtime' => 'nullable|numeric',
            'items.*.transfer_id' => 'nullable|string',
            'items.*.transfer_date' => 'nullable|date',
            'items.*.transfer_status' => 'nullable|in:pending,completed,failed',
            'items.*.note' => 'nullable|string',
            'items.*.advance_settled' => 'nullable|numeric|min:0',
            'items.*.advance_before_settle' => 'nullable|numeric|min:0',
        ]);

        $month = $data['month'];
        $monthFormatted = str_replace('-', '', $month);
        $year = (int) substr($month, 0, 4);

        $run = PayrollRun::updateOrCreate(
            ['period_type' => 'monthly', 'month' => $month],
            [
                'year' => $year,
                'week_number' => 0 .$monthFormatted,
                'status' => 'draft',
                'created_by' => auth()->id(),
                'generated_at' => now(),
                'period_type' => 'monthly',
                'month' => $month,
            ]
        );

        foreach ($data['items'] as $row) {
            $gross = (float) $row['gross'];
            $cash = (float) ($row['cash'] ?? 0);
            $bankFixTotal = (float) ($row['bank_fix_total'] ?? 0);

            $advanceSettled = (float) ($row['advance_settled'] ?? 0);
            $advanceBeforeSettle = (float) ($row['advance_before_settle'] ?? 0);
            // Cap settlement at both outstanding balance and available bank
            $advanceSettled = min($advanceSettled, $advanceBeforeSettle, $bankFixTotal);
            $advanceSettled = max(0, $advanceSettled);
            $advanceBalance = round(max(0, $advanceBeforeSettle - $advanceSettled), 2);

            // Monthly settlement reduces bank transfer, NOT cash (cash already paid weekly)
            $bank = round(max(0, $bankFixTotal - $advanceSettled), 2);
            // Fallback if bank_fix_total was not sent (old form submissions)
            if ($bankFixTotal <= 0) {
                $bank = (float) ($row['bank'] ?? max(0, $gross - $cash));
            }

            $payload = [
                'payroll_run_id' => $run->id,
                'employee_id' => $row['employee_id'],
                'type' => $row['type'],
                'total_days' => $row['total_days'] ?? null,
                'present_days' => $row['present_days'] ?? null,
                'total_hours' => $row['total_hours'] ?? null,
                'gross_amount' => $gross,
                'cash_amount' => $cash,
                'bank_amount' => $bank,
                'transfer_id' => $row['transfer_id'] ?? null,
                'transfer_date' => $row['transfer_date'] ?? null,
                'transfer_status' => $row['transfer_status'] ?? null,
                'note' => $row['note'] ?? null,
                // advance settlement stored here; carry-forward query reads this
                'advance_recovered' => round($advanceSettled, 2),
                'advance_balance' => $advanceBalance,
            ];

            if (($row['type'] ?? '') === 'daily_rate') {
                $payload['overtime_amount'] = $row['overtime'] ?? 0;
                $payload['overtime_hours'] = null;
            } else {
                $payload['overtime_hours'] = $row['overtime'] ?? 0;
                $payload['overtime_amount'] = null;
            }

            PayrollItem::updateOrCreate([
                'payroll_run_id' => $run->id,
                'employee_id' => $row['employee_id'],
            ], $payload);
        }

        return redirect()->route('payroll.monthly.index', ['month' => $month])
            ->with('success', 'Monthly payroll saved');
    }

    public function exportMonthXlsx(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m'));
        $rows = $this->buildMonthRows($month);

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Monthly Payroll');

        // Palette (Colors)
        $navy = '0F172A';
        $slate = '334155';
        $lighter = 'F8FAFC';
        $white = 'FFFFFF';
        $border = 'E2E8F0';

        // Headers (updated to reflect your latest changes)
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
        $sheet->setCellValue('A1', 'MONTHLY PAYROLL '.$month);
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

        // Column Widths
        $sheet->getColumnDimension('B')->setWidth(22);
        $sheet->getColumnDimension('D')->setWidth(18);
        $sheet->getColumnDimension('H')->setWidth(30);
        $sheet->getStyle('H:H')->getAlignment()->setWrapText(true);

        // Data Rows
        $rowIndex = 4;
        foreach ($rows as $r) {
            $emp = $r['employee'];

            // Set employee data
            $this->setCell($sheet, 1, $rowIndex, $emp->employee_code);
            $this->setCell($sheet, 2, $rowIndex, $emp->name);
            $this->setCell($sheet, 3, $rowIndex, $r['type'] === 'daily_rate' ? 'Daily' : 'Hourly');
            $this->setCell($sheet, 4, $rowIndex, $r['weeks_display'] ?? ''); // Weeks display, if relevant

            // Set financial data
            $this->setCell($sheet, 5, $rowIndex, (float) ($r['gross_amount'] ?? 0));
            $this->setCell($sheet, 6, $rowIndex, (float) ($r['cash_amount'] ?? 0));
            $this->setCell($sheet, 7, $rowIndex, (float) ($r['bank_amount'] ?? 0));

            // Set additional data: note, transfer ID, date, status
            $this->setCell($sheet, 8, $rowIndex, $r['note'] ?? '');
            $this->setCell($sheet, 9, $rowIndex, $r['transfer_id'] ?? '');

            // Transfer date (formatted for Excel)
            if (! empty($r['transfer_date'])) {
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

            // Transfer status (pending, completed, failed)
            $this->setCell($sheet, 11, $rowIndex, $r['transfer_status'] ?? '');

            // Move to the next row
            $rowIndex++;
        }

        // Last Row calculation
        $lastRow = max(4, $rowIndex - 1);
        $dataRange = "A4:{$lastColLetter}{$lastRow}";

        $sheet->getStyle($dataRange)->applyFromArray([
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => $border]],
            ],
        ]);

        // Zebra Striping (alternating row color)
        for ($r = 4; $r <= $lastRow; $r++) {
            if (($r % 2) === 0) {
                $sheet->getStyle("A{$r}:{$lastColLetter}{$r}")
                    ->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setRGB($lighter);
            }
        }

        // Money Format for columns E, F, G (Gross, Cash, Bank)
        $sheet->getStyle("E4:G{$lastRow}")
            ->getNumberFormat()
            ->setFormatCode(NumberFormat::FORMAT_NUMBER_00);

        // Align money values to the right
        $sheet->getStyle("E4:G{$lastRow}")
            ->getAlignment()
            ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

        // Conditional formatting for transfer status
        $statusRange = "K4:K{$lastRow}";

        // Pending Status Style
        $condPending = new Conditional;
        $condPending->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
        $condPending->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
        $condPending->setText('pending');
        $condPending->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
        $condPending->getStyle()->getFont()->getColor()->setRGB('92400E');

        // Completed Status Style
        $condCompleted = new Conditional;
        $condCompleted->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
        $condCompleted->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
        $condCompleted->setText('completed');
        $condCompleted->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCFCE7');
        $condCompleted->getStyle()->getFont()->getColor()->setRGB('166534');

        // Failed Status Style
        $condFailed = new Conditional;
        $condFailed->setConditionType(Conditional::CONDITION_CONTAINSTEXT);
        $condFailed->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT);
        $condFailed->setText('failed');
        $condFailed->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
        $condFailed->getStyle()->getFont()->getColor()->setRGB('991B1B');

        $sheet->getStyle($statusRange)->setConditionalStyles([$condPending, $condCompleted, $condFailed]);

        // Autosize columns A to K
        foreach (range('A', 'K') as $col) {
            if (in_array($col, ['B', 'D', 'H'], true)) {
                continue;
            }
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Export filename
        $fileName = "payroll_month_{$month}.xlsx";
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    protected function setCell($sheet, int $colIndex, int $rowIndex, $value)
    {
        $colLetter = Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue("{$colLetter}{$rowIndex}", $value);
    }

    /**
     * MISSING-02: Finalize a monthly payroll run (set status = 'final').
     */
    public function finalizeMonth(Request $request)
    {
        $data = $request->validate([
            'month' => 'required|string|regex:/^\d{4}-\d{2}$/',
        ]);

        $run = PayrollRun::where('period_type', 'monthly')
            ->whereRaw("DATE_FORMAT(period_start, '%Y-%m') = ?", [$data['month']])
            ->first();

        if (! $run) {
            return back()->withErrors(['finalize' => 'No payroll run found for this month. Save payroll first.']);
        }

        $run->status = 'final';
        $run->save();

        return redirect()->route('payroll.monthly.index', ['month' => $data['month']])
            ->with('success', 'Monthly payroll for '.$data['month'].' has been finalized and locked.');
    }

    /**
     * Compute the advance carry-over balance FROM the previous month into the given month.
     *
     * Looks at the last week of the previous month (same lookback window as weekly carry-forward)
     * and computes the running balance EXCLUDING any weeks that are already IN the given month.
     * Also subtracts any prior monthly settlements.
     */
    private function getMonthAdvanceCarryOver(int $empId, string $month): float
    {
        $monthStart = Carbon::parse($month.'-01')->startOfMonth();

        // First Monday that falls in the current month
        $firstMondayInMonth = $monthStart->copy();
        while ((int) $firstMondayInMonth->dayOfWeek !== 1) {
            $firstMondayInMonth->addDay();
        }

        $rows = DB::table('payroll_items as pi')
            ->join('payroll_runs as pr', 'pr.id', '=', 'pi.payroll_run_id')
            ->where('pi.employee_id', $empId)
            ->where(function ($q) {
                $q->where('pr.period_type', 'weekly')->orWhereNull('pr.period_type');
            })
            ->orderBy('pr.year')
            ->orderBy('pr.week_number')
            ->select('pi.bank_amount', 'pi.weekly_amount', 'pi.advance_given', 'pi.advance_recovered', 'pr.year', 'pr.week_number')
            ->get();

        $balance = 0.0;
        foreach ($rows as $row) {
            $itemMonday = Carbon::create()->setISODate($row->year, $row->week_number, 1)->startOfDay();
            // Only weeks strictly before this month's first Monday
            if ($itemMonday->gte($firstMondayInMonth)) {
                continue;
            }
            $given = max(0.0, (float) ($row->bank_amount ?? 0) - (float) ($row->weekly_amount ?? 0));
            $recovered = (float) ($row->advance_recovered ?? 0);
            $balance = max(0.0, $balance + $given - $recovered);
        }

        // Also subtract any prior monthly settlements (months before this one)
        $priorSettled = DB::table('payroll_items as pi')
            ->join('payroll_runs as pr', 'pr.id', '=', 'pi.payroll_run_id')
            ->where('pi.employee_id', $empId)
            ->where('pr.period_type', 'monthly')
            ->where('pr.month', '<', $month)
            ->sum('pi.advance_recovered');

        return max(0.0, $balance - (float) $priorSettled);
    }
}
