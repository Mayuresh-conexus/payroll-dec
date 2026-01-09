<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
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

    /**
     * Weeks that overlap the selected month.
     * Uses ISO week conversion: (year, week_number) -> week start/end dates.
     */
   protected function getWeeksForMonth(string $month): array
{
    $mStart = Carbon::createFromFormat('Y-m-d', $month . '-01')->startOfMonth();
    $mEnd   = $mStart->copy()->endOfMonth();

    $runs = PayrollRun::query()
        ->where('period_type', 'weekly')
        ->whereNotNull('week_number')
        ->where('week_number', '>', 0)
        ->where('year', (int) $mStart->year) // usually enough, optional
        ->get(['year', 'week_number']);

    $weeks = [];

    foreach ($runs as $r) {
        $weekStart = Carbon::create()->setISODate((int)$r->year, (int)$r->week_number)->startOfWeek(Carbon::MONDAY);
        $weekEnd   = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        // include week if it overlaps the month
        if ($weekStart->lte($mEnd) && $weekEnd->gte($mStart)) {
            $weeks[] = (int) $r->week_number;
        }
    }

    $weeks = array_values(array_unique($weeks));
    sort($weeks);

    return $weeks;
}


    /**
     * Map: employee_id|type => week_number => sums
     * Only includes weekly runs whose weeks overlap the selected month.
     */
    protected function buildEmployeeWeekMap(string $month): array
    {
        $weeks = $this->getWeeksForMonth($month);
        if (empty($weeks)) return [];

        $mStart = Carbon::createFromFormat('Y-m-d', $month . '-01');

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
        $run = $it->payrollRun; // use eager loaded relation
        if (!$run) continue;

        $w = (int) ($run->week_number ?? 0);
        if ($w <= 0) continue;

        $k = $it->employee_id . '|' . $it->type;

        if (!isset($map[$k])) $map[$k] = [];
        if (!isset($map[$k][$w])) {
            $map[$k][$w] = ['gross' => 0.0, 'cash' => 0.0, 'bank' => 0.0];
        }

        $map[$k][$w]['gross'] += (float) ($it->gross_amount ?? 0);
        $map[$k][$w]['cash']  += (float) ($it->cash_amount ?? 0);
        $map[$k][$w]['bank']  += (float) ($it->bank_amount ?? 0);
}


        return $map;
    }

    protected function buildMonthRows(string $month)
    {
        $start = Carbon::parse($month . '-01')->startOfMonth();
        $end   = $start->copy()->endOfMonth();

        $monthWeeks = $this->getWeeksForMonth($month);
        $weekMap = $this->buildEmployeeWeekMap($month);

        $weeks = $this->getWeeksForMonth($month);

        $weeklyRunIds = PayrollRun::query()
        ->where('period_type', 'weekly')
        ->where('year', (int) $start->year)
        ->whereIn('week_number', $monthWeeks)
        ->pluck('id')
        ->all();

        // Include weekly runs created in the month AND monthly runs explicitly for this month
        $items = PayrollItem::with(['employee', 'payrollRun'])
            ->where(function ($q) use ($weeklyRunIds, $month) {

                // weekly items that belong to overlapping weeks
                if (!empty($weeklyRunIds)) {
                    $q->whereIn('payroll_run_id', $weeklyRunIds);
                }

                // also include monthly run items for this month
                $q->orWhereHas('payrollRun', function ($qr) use ($month) {
                    $qr->where('period_type', 'monthly')->where('month', $month);
                });
            })
            ->get();

        $grouped = $items->groupBy(function ($it) {
            return $it->employee_id . '|' . $it->type;
        });

        $rows = collect();

        foreach ($grouped as $key => $group) {
            $sample = $group->first();
            $emp = $sample->employee;
            if (!$emp) continue;

            // Prefer monthly items for totals when available
            $monthlyItems = $group->filter(function ($it) use ($month) {
                return $it->payrollRun
                    && ($it->payrollRun->period_type === 'monthly')
                    && ($it->payrollRun->month === $month);
            });

            $useSet = $monthlyItems->count() ? $monthlyItems : $group;

            // Weeks display should come from weekly map for this month
            $wkKey = $emp->id . '|' . $sample->type;
            $empWeeks = [];

            foreach ($monthWeeks as $w) {
                if (!empty($weekMap[$wkKey][$w])) {
                    $empWeeks[] = (int) $w;
                }
            }

            $weeksDisplay = '';
            if (!empty($empWeeks)) {
                $weeksDisplay = implode(' ', array_map(function ($w) {
                    return 'wk' . $w;
                }, $empWeeks));
            }

            $rows->push([
                'employee' => $emp,
                'type' => $sample->type,

                'present_days' => $useSet->sum('present_days'),
                'total_days' => $useSet->sum('total_days'),
                'total_hours' => $useSet->sum('total_hours'),

                'overtime_hours' => $useSet->sum('overtime_hours'),
                'overtime_amount' => $useSet->sum('overtime_amount'),

                'gross_amount' => $useSet->sum('gross_amount'),
                'cash_amount' => $useSet->sum('cash_amount'),
                'bank_amount' => $useSet->sum('bank_amount'),

                'transfer_id' => $useSet->pluck('transfer_id')->filter()->first() ?? null,
                'transfer_date' => $useSet->pluck('transfer_date')->filter()->first() ?? null,
                'transfer_status' => $useSet->pluck('transfer_status')->filter()->first() ?? null,
                'note' => $useSet->pluck('note')->filter()->first() ?? null,

                'weeks' => $empWeeks,
                'weeks_display' => $weeksDisplay,
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
            'items.*.overtime' => 'nullable|numeric',
            'items.*.transfer_id' => 'nullable|string',
            'items.*.transfer_date' => 'nullable|date',
            'items.*.transfer_status' => 'nullable|in:pending,completed,failed',
            'items.*.note' => 'nullable|string',
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

    protected function setCell($sheet, int $colIndex, int $rowIndex, $value): void
    {
        $letter = Coordinate::stringFromColumnIndex($colIndex);
        $sheet->setCellValue($letter . $rowIndex, $value);
    }

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
