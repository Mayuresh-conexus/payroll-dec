<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveWeekPayrollRequest;
use App\Models\AuditLog;
use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\HourlyAttendance;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Services\AttendanceService;
use App\Services\BankHolidayService;
use App\Services\PayrollService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PayrollController extends Controller
{
    public function index(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $week = (int) $request->input('week', now()->weekOfYear);
        $weeksInYear = Carbon::create($year, 12, 28)->isoWeek();
        $month = Carbon::now()->setISODate($year, $week)->month;

        $dailyLockedWeek = DailyRateAttendance::where('year', $year)
            ->where('week_number', $week)->where('locked', true)->exists();
        $hourlyLockedWeek = HourlyAttendance::where('year', $year)
            ->where('week_number', $week)->where('locked', true)->exists();

        $run = PayrollRun::with('items.employee')
            ->where('year', $year)->where('week_number', $week)->first();

        $service = app(PayrollService::class);
        $rawRows = $service->buildRowsFromAttendance($year, $week);
        $rows = $service->mergeWithPayrollRun($rawRows, $run, $dailyLockedWeek, $hourlyLockedWeek);

        $totals = [
            'gross' => $rows->sum('gross_amount'),
            'cash' => $rows->sum('cash_amount'),
            'bank' => $rows->sum('bank_amount'),
        ];

        $history = $run
            ? AuditLog::where('model_type', 'PayrollRun')
                ->where('model_id', $run->id)
                ->with('user')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get()
            : collect();

        // Holidays clear once a month, so the BH columns only belong on the week
        // that settles a month which actually had one.
        $showBankHoliday = app(BankHolidayService::class)->monthHasHoliday($year, $week);

        // The leave column only earns its place in a week where someone took some.
        $showLeave = $rows->contains(fn (array $row): bool => (float) ($row['leave_amount'] ?? 0) > 0);

        return view('payroll.index', [
            'year' => $year,
            'week' => $week,
            'showBankHoliday' => $showBankHoliday,
            'showLeave' => $showLeave,
            'month' => $month,
            'run' => $run,
            'rows' => $rows,
            'totals' => $totals,
            'weeksInYear' => $weeksInYear,
            'history' => $history,
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
                'status' => 'draft',
                'period_type' => 'weekly',
                'created_by' => auth()->id(),
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
            $cash = (float) ($row['cash'] ?? 0);
            $bank = (float) ($row['bank'] ?? 0);

            // Allow bank > weekly (advance scenario). When bank exceeds earnings, cash must be 0.
            // Otherwise cap so cash + bank never exceeds weekly.
            if ($bank > $weeklyAmount) {
                $cash = 0;
            } elseif ($cash + $bank > $weeklyAmount) {
                $bank = max(0, $weeklyAmount - $cash);
            }

            $emp = Employee::find($row['employee_id']);
            $weekStart = Carbon::now()->setISODate($data['year'], $data['week'], 1);

            // Advance balance:
            //   advance_given     = max(0, bank - earned)        — advance weeks only
            //   advance_recovered = explicit recover input       — cash deduction by admin
            //   new_balance       = max(0, prev + given - recovered)
            $prevBalance = (float) ($row['prev_advance_balance'] ?? 0);
            $availableCash = max(0, $weeklyAmount - $bank);
            $advanceGiven = round(max(0, $bank - $weeklyAmount), 2);
            $advanceRecovered = round(min((float) ($row['recover'] ?? 0), min($prevBalance, $availableCash)), 2);
            $advanceBalance = round(max(0, $prevBalance + $advanceGiven - $advanceRecovered), 2);

            PayrollItem::updateOrCreate(
                ['payroll_run_id' => $run->id, 'employee_id' => $row['employee_id']],
                [
                    'type' => $row['type'],
                    'total_days' => $row['total_days'] ?? null,
                    'present_days' => $row['present_days'] ?? null,
                    'total_hours' => $row['total_hours'] ?? null,
                    'weekly_amount' => $weeklyAmount,
                    'gross_amount' => $weeklyAmount,
                    'cash_amount' => $cash,
                    'bank_amount' => $bank,
                    'overtime_amount' => $row['type'] === 'daily_rate' ? ($row['overtime'] ?? 0) : null,
                    'overtime_hours' => $row['type'] === 'hourly' ? ($row['overtime'] ?? 0) : null,
                    'applied_daily_rate' => $emp ? ($emp->rateAt($weekStart, 'daily_rate') ?? $emp->daily_rate) : null,
                    'applied_hourly_rate' => $emp ? ($emp->rateAt($weekStart, 'hourly_rate') ?? $emp->hourly_rate) : null,
                    'applied_hours_per_day' => $emp ? ($emp->rateAt($weekStart, 'hours_per_day') ?? $emp->hours_per_day) : null,
                    'bh_amount' => round((float) ($row['bh_amount'] ?? 0), 2),
                    'bh_cash' => round((float) ($row['bh_cash'] ?? 0), 2),
                    'bh_bank' => round((float) ($row['bh_bank'] ?? 0), 2),
                    'applied_bh_bank_percent' => $emp?->bh_bank_percent,
                    'leave_days' => round((float) ($row['leave_days'] ?? 0), 2),
                    'leave_hours' => round((float) ($row['leave_hours'] ?? 0), 2),
                    'leave_amount' => round((float) ($row['leave_amount'] ?? 0), 2),
                    'advance_given' => round($advanceGiven, 2),
                    'advance_recovered' => round($advanceRecovered, 2),
                    'advance_balance' => $advanceBalance,
                ]
            );
        }

        // Build per-employee diff and write one rich audit entry
        $diffs = [];
        foreach ($data['items'] as $row) {
            $old = $before->get($row['employee_id']);
            $name = $old?->employee?->name ?? Employee::find($row['employee_id'])?->name ?? "Employee #{$row['employee_id']}";
            $newW = round((float) ($row['weekly_amount'] ?? 0), 2);
            $newC = round((float) ($row['cash'] ?? 0), 2);
            $newB = round((float) ($row['bank'] ?? 0), 2);
            $entry = ['name' => $name];

            if ($old) {
                $oldW = round((float) $old->weekly_amount, 2);
                $oldC = round((float) $old->cash_amount, 2);
                $oldB = round((float) $old->bank_amount, 2);
                if ($oldW !== $newW) {
                    $entry['weekly'] = ['from' => $oldW, 'to' => $newW];
                }
                if ($oldC !== $newC) {
                    $entry['cash'] = ['from' => $oldC, 'to' => $newC];
                }
                if ($oldB !== $newB) {
                    $entry['bank'] = ['from' => $oldB, 'to' => $newB];
                }
            } else {
                $entry['weekly'] = ['from' => null, 'to' => $newW];
                $entry['cash'] = ['from' => null, 'to' => $newC];
                $entry['bank'] = ['from' => null, 'to' => $newB];
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
                'days' => $att->days_map ?? [],
                'overtime_map' => $att->overtime_map ?? [],
            ], $data['year'], $data['week'], (bool) ($att->locked ?? false));
        }

        $hourlyAtts = HourlyAttendance::where('year', $data['year'])
            ->where('week_number', $data['week'])
            ->get();

        foreach ($hourlyAtts as $att) {
            // Deliberately no ot_map: this is a recalculation, so overtime is
            // re-derived from the logged hours. Passing the stored value back in
            // would carry forward any figure a previous save had already inflated.
            $attService->saveHourlyEmployee($att->employee_id, [
                'hours_map' => $att->hours_map ?? [],
                'days' => [],
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
                $old = $before->get($item->employee_id);
                $name = $item->employee?->name ?? "Employee #{$item->employee_id}";
                $newW = round((float) $item->weekly_amount, 2);
                $newC = round((float) $item->cash_amount, 2);
                $newB = round((float) $item->bank_amount, 2);
                $entry = ['name' => $name];

                if ($old) {
                    $oldW = round((float) $old->weekly_amount, 2);
                    $oldC = round((float) $old->cash_amount, 2);
                    $oldB = round((float) $old->bank_amount, 2);
                    if ($oldW !== $newW) {
                        $entry['weekly'] = ['from' => $oldW, 'to' => $newW];
                    }
                    if ($oldC !== $newC) {
                        $entry['cash'] = ['from' => $oldC, 'to' => $newC];
                    }
                    if ($oldB !== $newB) {
                        $entry['bank'] = ['from' => $oldB, 'to' => $newB];
                    }
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

        if (! $run) {
            return back()->withErrors(['finalize' => 'No payroll run found for this week. Save payroll first.']);
        }

        $run->status = 'final';
        $run->save();

        return redirect()->route('payroll.index', [
            'year' => $data['year'],
            'week' => $data['week'],
        ])->with('success', 'Payroll for week '.$data['week'].' has been finalized and is now locked.');
    }

    /**
     * Revert a finalized weekly payroll run back to draft status.
     * Only admin can do this. Allows corrections after finalization.
     */
    public function revertWeek(Request $request)
    {
        $data = $request->validate([
            'year' => 'required|integer',
            'week' => 'required|integer|min:1|max:53',
        ]);

        $run = PayrollRun::where('period_type', 'weekly')
            ->where('year', $data['year'])
            ->where('week_number', $data['week'])
            ->first();

        if (! $run) {
            return back()->withErrors(['revert' => 'No payroll run found for this week.']);
        }

        $run->status = 'draft';
        $run->save();

        return redirect()->route('payroll.index', [
            'year' => $data['year'],
            'week' => $data['week'],
        ])->with('success', 'Payroll for week '.$data['week'].' has been reverted to draft and is now editable.');
    }

    /**
     * Weekly payroll export CSV, now including day wise IN / OFF columns
     */
    /**
     * Resolve the saved PayrollRun, the raw attendance for the week (keyed by
     * employee_id), and the authoritative service-computed rows (cash/bank/weekly/
     * arrears) — the single source of truth shared by the web page and every
     * export format, so they never disagree.
     *
     * @return array{0: PayrollRun, 1: \Illuminate\Support\Collection, 2: \Illuminate\Support\Collection, 3: \Illuminate\Support\Collection}
     */
    private function resolveWeekExportData(int $year, int $week): array
    {
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

        $dailyLockedWeek = $dailyAttendance->contains(fn ($att) => $att->locked);
        $hourlyLockedWeek = $hourlyAttendance->contains(fn ($att) => $att->locked);

        // Source cash/bank/weekly/arrears from the same computed rows the web page
        // displays — reading $item->cash_amount directly would show 0 for any week
        // whose attendance was saved but never explicitly finalized via "Save Weekly
        // Payroll" (AttendanceService always persists cash_amount=0 as a placeholder).
        $service = app(PayrollService::class);
        $rawRows = $service->buildRowsFromAttendance($year, $week);
        $rows = $service->mergeWithPayrollRun($rawRows, $run, $dailyLockedWeek, $hourlyLockedWeek);

        return [$run, $rows, $dailyAttendance, $hourlyAttendance];
    }

    /**
     * Column layout of the weekly sheet.
     *
     * Mirrors the workbook the client has kept by hand for years: an attendance
     * block on the left, a red divider, then the pay block on the right, with the
     * employee name repeated either side so a wide row stays readable.
     *
     * @var array<string, string>
     */
    /**
     * Column letters for the weekly sheet.
     *
     * Bank-holiday pay clears once a month, so its two columns are only present
     * on the settling week — and their absence shifts Comments and Check left.
     *
     * @return array<string, string>
     */
    private function weekSheetColumns(bool $withBankHoliday): array
    {
        $columns = [
            'no' => 'A',
            'name_left' => 'B',
            'ot' => 'J',
            'total_wd' => 'K',
            'total_wh' => 'L',
            'redline' => 'M',
            'name_right' => 'N',
            'rate' => 'O',
            'total_weekly' => 'P',
            'cash' => 'Q',
            'bank_weekly' => 'R',
            'bank_monthly' => 'S',
        ];

        $next = 'T';

        if ($withBankHoliday) {
            $columns['bh_cash'] = 'T';
            $columns['bh_bank'] = 'U';
            $next = 'V';
        }

        $columns['comments'] = $next;
        $columns['check'] = chr(ord($next) + 1);

        return $columns;
    }

    private const WEEK_SHEET_DAY_COLUMNS = ['C', 'D', 'E', 'F', 'G', 'H', 'I'];

    private const WEEK_SHEET_DAY_KEYS = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

    /** Shared with the CLOSED day cells so the sheet uses one blue throughout. */
    private const WEEK_SHEET_BLUE = 'FF0C4D90';

    /**
     * Heading fill and text colour per column, so a column is findable by colour
     * on a row this wide. Anything unlisted falls back to grey.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const WEEK_SHEET_HEADER_COLOURS = [
        'no' => ['FF000000', 'FFFFFFFF'],
        'name_left' => ['FF000000', 'FFFFFFFF'],
        'name_right' => ['FF000000', 'FFFFFFFF'],
        'ot' => ['FFDC2626', 'FFFFFFFF'],
        'total_wd' => [self::WEEK_SHEET_BLUE, 'FFFFFFFF'],
        'total_wh' => [self::WEEK_SHEET_BLUE, 'FFFFFFFF'],
        'cash' => ['FFFFC000', 'FF000000'],
        'check' => ['FFFFC000', 'FF000000'],
        'bh_cash' => ['FF7C3AED', 'FFFFFFFF'],
        'bh_bank' => ['FF7C3AED', 'FFFFFFFF'],
    ];

    private const WEEK_SHEET_HEADER_DEFAULT = ['FFD9D9D9', 'FF000000'];

    public function exportWeekCsv(Request $request): StreamedResponse
    {
        $year = (int) $request->input('year', now()->year);
        $week = (int) $request->input('week', now()->weekOfYear);

        [$run, $rows, $dailyAttendance, $hourlyAttendance] = $this->resolveWeekExportData($year, $week);
        $itemsByEmployee = $run->items->keyBy('employee_id');

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle("Week {$week}");

        $monday = Carbon::now()->setISODate($year, $week, 1);
        // Holidays clear once a month; the columns only belong on the settling week.
        $showBankHoliday = app(BankHolidayService::class)->monthHasHoliday($year, $week);
        $col = $this->weekSheetColumns($showBankHoliday);

        // Bank holidays are the same for everyone, so resolve them once per sheet.
        $holidayMap = Holiday::mapForWeek($monday);

        $this->writeWeekSheetHeader($sheet, $week, $monday, $holidayMap, $col);

        // Iterating the computed rows rather than $run->items keeps the sheet in the
        // same employee-code order as the payroll page.
        $rowIndex = 3;
        $number = 1;
        $totals = ['total_weekly' => 0.0, 'cash' => 0.0, 'bank_weekly' => 0.0, 'bank_monthly' => 0.0, 'bh_cash' => 0.0, 'bh_bank' => 0.0];

        foreach ($rows as $row) {
            $employee = $row['employee'];
            if (! $employee) {
                continue;
            }

            $item = $itemsByEmployee->get($employee->id);
            $isDaily = ($row['type'] ?? null) === 'daily_rate';

            $sheet->setCellValue($col['no'].$rowIndex, $number);
            $sheet->setCellValue($col['name_left'].$rowIndex, $employee->name);
            $sheet->setCellValue($col['name_right'].$rowIndex, $employee->name);

            $this->writeWeekSheetDays($sheet, $rowIndex, $row, $employee, $monday, $dailyAttendance, $hourlyAttendance, $holidayMap);

            // OT carries the unit each pay type is measured in: a money amount for
            // daily staff (their overtime is entered as cash) and hours for hourly.
            // Counts of hours and days use General so a whole number prints as "50",
            // not "50." — Excel renders a trailing separator for 0.## formats.
            $overtime = $isDaily
                ? (float) ($row['overtime_amount'] ?? $item?->overtime_amount ?? 0)
                : (float) ($row['overtime_hours'] ?? $item?->overtime_hours ?? 0);
            $this->setWeekSheetNumber($sheet, $col['ot'].$rowIndex, $overtime, $isDaily ? '#,##0.00' : 'General');

            [$workedDays, $workedHours] = $this->weekSheetTotals($row, $employee, $dailyAttendance, $hourlyAttendance);
            $this->setWeekSheetNumber($sheet, $col['total_wd'].$rowIndex, $workedDays, 'General');

            if ($isDaily) {
                $sheet->setCellValue($col['total_wh'].$rowIndex, '-');
            } else {
                $this->setWeekSheetNumber($sheet, $col['total_wh'].$rowIndex, $workedHours, 'General');
            }

            // The three summary columns carry the same blue down the sheet, so the
            // totals read as one block against the day-by-day detail on their left.
            $summaryRange = "{$col['ot']}{$rowIndex}:{$col['total_wh']}{$rowIndex}";
            $sheet->getStyle($summaryRange)->getFill()
                ->setFillType(Fill::FILL_SOLID)
                ->getStartColor()->setARGB(self::WEEK_SHEET_BLUE);
            $sheet->getStyle($summaryRange)->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');

            $rate = (float) ($row['rate'] ?? ($isDaily ? $employee->daily_rate : $employee->hourly_rate) ?? 0);
            $totalWeekly = (float) ($row['weekly_amount'] ?? $item?->weekly_amount ?? 0);
            $cash = (float) ($row['cash_amount'] ?? $item?->cash_amount ?? 0);
            $bankWeekly = (float) ($row['bank_amount'] ?? $item?->bank_amount ?? 0);

            $this->setWeekSheetNumber($sheet, $col['rate'].$rowIndex, $rate, '#,##0.00');
            $this->setWeekSheetNumber($sheet, $col['total_weekly'].$rowIndex, $totalWeekly, '#,##0.00');
            $this->setWeekSheetNumber($sheet, $col['cash'].$rowIndex, $cash, '#,##0.00');
            $this->setWeekSheetNumber($sheet, $col['bank_weekly'].$rowIndex, $bankWeekly, '#,##0.00');

            // Bank Monthly annualises the fixed weekly transfer (x52/12). Only the
            // daily-rate staff are on a standing order, so hourly rows stay blank —
            // their bank figure moves with the hours and has no monthly equivalent.
            $bankMonthly = 0.0;
            if ($isDaily && $bankWeekly != 0.0) {
                $bankMonthly = round($bankWeekly * 52 / 12, 2);
                $this->setWeekSheetNumber($sheet, $col['bank_monthly'].$rowIndex, $bankMonthly, '#,##0.00');
            } else {
                $sheet->setCellValue($col['bank_monthly'].$rowIndex, '-');
            }

            // Bank-holiday double pay for the month, cleared in this week. The cash
            // share sits inside CASH; the bank share is a transfer of its own.
            $bhCash = $showBankHoliday ? (float) ($row['bh_cash'] ?? 0) : 0.0;
            $bhBank = $showBankHoliday ? (float) ($row['bh_bank'] ?? 0) : 0.0;

            if (! $showBankHoliday) {
                // no BH columns on this sheet
            } elseif ($bhCash > 0 || $bhBank > 0) {
                $this->setWeekSheetNumber($sheet, $col['bh_cash'].$rowIndex, $bhCash, '#,##0.00');
                $this->setWeekSheetNumber($sheet, $col['bh_bank'].$rowIndex, $bhBank, '#,##0.00');
            } else {
                $sheet->setCellValue($col['bh_cash'].$rowIndex, '-');
                $sheet->setCellValue($col['bh_bank'].$rowIndex, '-');
                $sheet->getStyle("{$col['bh_cash']}{$rowIndex}:{$col['bh_bank']}{$rowIndex}")
                    ->getAlignment()->setHorizontal('center');
            }

            $sheet->setCellValue($col['comments'].$rowIndex, $item?->note ?? '');
            $this->writeWeekSheetCheckCell($sheet, $col['check'].$rowIndex);

            $totals['total_weekly'] += $totalWeekly;
            $totals['cash'] += $cash;
            $totals['bank_weekly'] += $bankWeekly;
            $totals['bank_monthly'] += $bankMonthly;
            $totals['bh_cash'] += $bhCash;
            $totals['bh_bank'] += $bhBank;

            $sheet->getStyle($col['no'].$rowIndex)->getAlignment()->setHorizontal('center');
            $sheet->getStyle("{$col['ot']}{$rowIndex}:{$col['total_wh']}{$rowIndex}")
                ->getAlignment()->setHorizontal('center');

            $rowIndex++;
            $number++;
        }

        $this->writeWeekSheetTotals($sheet, $rowIndex, $totals, $col);
        $this->applyWeekSheetLayout($sheet, $rowIndex, $col);

        $fileName = "payroll_week_{$week}_{$year}.xlsx";
        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer): void {
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Two-row banner: WEEK n and the dates on top, field names underneath.
     */
    private function writeWeekSheetHeader(Worksheet $sheet, int $week, Carbon $monday, array $holidayMap, array $col): void
    {

        $sheet->setCellValue($col['name_left'].'1', 'WEEK '.$week);
        $sheet->setCellValue($col['name_left'].'2', 'Employee Name');
        $sheet->setCellValue($col['name_right'].'1', 'WEEK '.$week);
        $sheet->setCellValue($col['name_right'].'2', 'Employee Name');

        foreach (self::WEEK_SHEET_DAY_COLUMNS as $index => $letter) {
            $date = $monday->copy()->addDays($index);
            $dayKey = self::WEEK_SHEET_DAY_KEYS[$index];
            $sheet->setCellValue($letter.'1', $date->format('d-M'));

            if (isset($holidayMap[$dayKey])) {
                $sheet->setCellValue($letter.'2', $date->format('D').' · BH');
                $sheet->getComment($letter.'2')->getText()->createTextRun($holidayMap[$dayKey]);
            } else {
                $sheet->setCellValue($letter.'2', $date->format('D'));
            }
        }

        // Everything without a date above it spans both header rows.
        $spanning = [
            $col['no'] => 'NO',
            $col['ot'] => 'OT',
            $col['total_wd'] => 'Total WD',
            $col['total_wh'] => 'Total WH',
            $col['redline'] => '',
            $col['rate'] => 'Rate',
            $col['total_weekly'] => 'Total Weekly',
            $col['cash'] => 'CASH',
            $col['bank_weekly'] => 'Bank Weekly',
            $col['bank_monthly'] => 'Bank Monthly',
        ];

        if (isset($col['bh_cash'])) {
            $spanning[$col['bh_cash']] = 'BH Cash';
            $spanning[$col['bh_bank']] = 'BH Bank';
        }

        $spanning[$col['comments']] = 'Comments';
        $spanning[$col['check']] = 'Check';

        foreach ($spanning as $letter => $label) {
            $sheet->setCellValue($letter.'1', $label);
            $sheet->mergeCells($letter.'1:'.$letter.'2');
        }

        $lastColumn = $col['check'];
        $sheet->getStyle("A1:{$lastColumn}2")->getFont()->setBold(true);
        $sheet->getStyle("A1:{$lastColumn}2")->getAlignment()
            ->setHorizontal('center')
            ->setVertical('center')
            ->setWrapText(true);

        foreach ($col as $key => $letter) {
            [$fill, $text] = self::WEEK_SHEET_HEADER_COLOURS[$key] ?? self::WEEK_SHEET_HEADER_DEFAULT;
            $this->paintWeekSheetHeaderCell($sheet, $letter, $fill, $text);
        }

        foreach (self::WEEK_SHEET_DAY_COLUMNS as $letter) {
            [$fill, $text] = self::WEEK_SHEET_HEADER_DEFAULT;
            $this->paintWeekSheetHeaderCell($sheet, $letter, $fill, $text);
        }

        $sheet->getRowDimension(1)->setRowHeight(18);
        $sheet->getRowDimension(2)->setRowHeight(18);
        $sheet->freezePane('C3');
    }

    private function paintWeekSheetHeaderCell(Worksheet $sheet, string $letter, string $fill, string $text): void
    {
        $range = "{$letter}1:{$letter}2";

        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB($fill);
        $sheet->getStyle($range)->getFont()->getColor()->setARGB($text);
    }

    /**
     * Fill the seven day cells for one employee.
     */
    private function writeWeekSheetDays(
        Worksheet $sheet,
        int $rowIndex,
        array $row,
        Employee $employee,
        Carbon $monday,
        $dailyAttendance,
        $hourlyAttendance,
        array $holidayMap = []
    ): void {
        $isDaily = ($row['type'] ?? null) === 'daily_rate';
        $dailyAtt = $dailyAttendance[$employee->id] ?? null;
        $hourlyAtt = $hourlyAttendance[$employee->id] ?? null;

        foreach (self::WEEK_SHEET_DAY_KEYS as $index => $dayKey) {
            $cell = self::WEEK_SHEET_DAY_COLUMNS[$index].$rowIndex;
            $date = $monday->copy()->addDays($index);

            if (! $employee->isPaidOn($date)) {
                $sheet->setCellValue($cell, '-');
                $sheet->getStyle($cell)->getFont()->getColor()->setARGB('FF9CA3AF');
                $sheet->getStyle($cell)->getAlignment()->setHorizontal('center');

                continue;
            }

            [$text, $fill, $fontColor] = $isDaily
                ? $this->weekSheetDailyCell($dailyAtt, $dayKey)
                : $this->weekSheetHourlyCell($hourlyAtt, $dayKey);

            // A day worked on a bank holiday earned double, so say so on the cell
            // itself — the money lands in the BH columns further right.
            if (isset($holidayMap[$dayKey])) {
                if (! in_array($text, ['OFF', 'CLOSED'], true)) {
                    $text .= ' BH';
                }
                $fill ??= 'FFEDE9FE';
            }

            $sheet->setCellValue($cell, $text);
            $sheet->getStyle($cell)->getAlignment()->setHorizontal('center')->setVertical('center');

            if ($fill !== null) {
                $sheet->getStyle($cell)->getFill()
                    ->setFillType(Fill::FILL_SOLID)
                    ->getStartColor()->setARGB($fill);
            }

            if ($fontColor !== null) {
                $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setARGB($fontColor);
            }
        }
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string} text, fill, font colour
     */
    private function weekSheetDailyCell(?DailyRateAttendance $att, string $dayKey): array
    {
        $daysMap = ($att && is_array($att->days_map)) ? $att->days_map : [];
        $present = (bool) ($daysMap[$dayKey] ?? false);

        if (! $present) {
            return $dayKey === 'sun'
                ? ['CLOSED', 'FF0C4D90', 'FFFFFFFF']
                : ['OFF', null, 'FFDC2626'];
        }

        $overtime = ($att && is_array($att->overtime_map)) ? (float) ($att->overtime_map[$dayKey] ?? 0) : 0.0;

        return [$overtime > 0 ? 'IN+'.rtrim(rtrim(number_format($overtime, 2), '0'), '.') : 'IN', null, null];
    }

    /**
     * @return array{0: string, 1: ?string, 2: ?string} text, fill, font colour
     */
    private function weekSheetHourlyCell(?HourlyAttendance $att, string $dayKey): array
    {
        $hours = ($att && is_array($att->hours_map)) ? (float) ($att->hours_map[$dayKey] ?? 0) : 0.0;
        $overtime = ($att && is_array($att->ot_map)) ? (float) ($att->ot_map[$dayKey] ?? 0) : 0.0;

        if ($hours <= 0) {
            return $dayKey === 'sun'
                ? ['CLOSED', 'FF0C4D90', 'FFFFFFFF']
                : ['OFF', null, 'FFDC2626'];
        }

        $label = $this->trimNumber($hours).'hrs';

        // The bracket spells out how the day splits, so the hours stay readable at a
        // glance while the regular/overtime breakdown is still on the row.
        if ($overtime > 0) {
            $label .= ' ('.$this->trimNumber($hours - $overtime).'+'.$this->trimNumber($overtime).')';
        }

        return [$label, null, null];
    }

    /**
     * @return array{0: float, 1: float} worked days, worked hours
     */
    private function weekSheetTotals(array $row, Employee $employee, $dailyAttendance, $hourlyAttendance): array
    {
        if (($row['type'] ?? null) === 'daily_rate') {
            return [(float) ($row['present_days'] ?? 0), 0.0];
        }

        $att = $hourlyAttendance[$employee->id] ?? null;
        $hoursMap = ($att && is_array($att->hours_map)) ? $att->hours_map : [];
        $workedDays = count(array_filter($hoursMap, fn ($h) => (float) $h > 0));

        $totalHours = (float) ($row['total_hours'] ?? 0) + (float) ($row['overtime_hours'] ?? 0);

        return [(float) $workedDays, $totalHours];
    }

    private function writeWeekSheetCheckCell(Worksheet $sheet, string $cell): void
    {
        // Starts as an unchecked "no" in Excel's own Bad styling; whoever reviews the
        // week overwrites it, exactly as they do on the sheet they keep by hand.
        $sheet->setCellValue($cell, 'no');
        $sheet->getStyle($cell)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFFC7CE');
        $sheet->getStyle($cell)->getFont()->setBold(true)->getColor()->setARGB('FF9C0006');
        $sheet->getStyle($cell)->getAlignment()->setHorizontal('center');
    }

    /**
     * @param  array<string, float>  $totals
     */
    private function writeWeekSheetTotals(Worksheet $sheet, int $rowIndex, array $totals, array $col): void
    {
        $sheet->setCellValue($col['name_right'].$rowIndex, 'TOTAL');

        $keys = ['total_weekly', 'cash', 'bank_weekly', 'bank_monthly'];

        if (isset($col['bh_cash'])) {
            $keys[] = 'bh_cash';
            $keys[] = 'bh_bank';
        }

        foreach ($keys as $key) {
            $this->setWeekSheetNumber($sheet, $col[$key].$rowIndex, $totals[$key], '#,##0.00');
        }

        $lastMoney = $col[$keys[count($keys) - 1]];
        $range = "{$col['name_right']}{$rowIndex}:{$lastMoney}{$rowIndex}";
        $sheet->getStyle($range)->getFont()->setBold(true);
        $sheet->getStyle($range)->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFF3F4F6');
    }

    private function applyWeekSheetLayout(Worksheet $sheet, int $rowIndexAfterData, array $col): void
    {
        $lastRow = $rowIndexAfterData;

        $widths = [
            $col['no'] => 5,
            $col['name_left'] => 26,
            $col['ot'] => 9,
            $col['total_wd'] => 9,
            $col['total_wh'] => 9,
            $col['redline'] => 2,
            $col['name_right'] => 26,
            $col['rate'] => 9,
            $col['total_weekly'] => 13,
            $col['cash'] => 12,
            $col['bank_weekly'] => 12,
            $col['bank_monthly'] => 13,
            $col['comments'] => 28,
            $col['check'] => 8,
        ];

        if (isset($col['bh_cash'])) {
            $widths[$col['bh_cash']] = 11;
            $widths[$col['bh_bank']] = 11;
        }

        foreach ($widths as $letter => $width) {
            $sheet->getColumnDimension($letter)->setWidth($width);
        }

        foreach (self::WEEK_SHEET_DAY_COLUMNS as $letter) {
            $sheet->getColumnDimension($letter)->setWidth(13);
        }

        // The red divider separates attendance from pay down the whole sheet.
        $sheet->getStyle("{$col['redline']}1:{$col['redline']}{$lastRow}")->getFill()
            ->setFillType(Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FFFF0000');

        $sheet->getStyle("A1:{$col['check']}{$lastRow}")
            ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
    }

    private function setWeekSheetNumber(Worksheet $sheet, string $cell, float $value, string $format): void
    {
        $sheet->setCellValue($cell, $value);
        $sheet->getStyle($cell)->getNumberFormat()->setFormatCode($format);
        $sheet->getStyle($cell)->getAlignment()->setHorizontal('center');
    }

    private function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    /**
     * Weekly payroll register as a printable PDF — a compact summary (no day-by-day
     * grid, which stays an Excel-only feature) suitable for printing/sharing.
     */
    public function exportWeekPdf(Request $request)
    {
        $year = (int) $request->input('year', now()->year);
        $week = (int) $request->input('week', now()->weekOfYear);

        [$run, $rows] = $this->resolveWeekExportData($year, $week);

        $totals = [
            'weekly' => $rows->sum('weekly_amount'),
            'cash' => $rows->sum('cash_amount'),
            'bank' => $rows->sum('bank_amount'),
            'bh_cash' => $rows->sum('bh_cash'),
            'bh_bank' => $rows->sum('bh_bank'),
            'arrears' => $rows->sum('arrears'),
        ];

        $pdf = Pdf::loadView('payroll.pdf.weekly', [
            'year' => $year,
            'week' => $week,
            'showBankHoliday' => app(BankHolidayService::class)->monthHasHoliday($year, $week),
            'run' => $run,
            'rows' => $rows,
            'totals' => $totals,
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download("payroll_week_{$week}_{$year}.pdf");
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
            'Content-Type' => 'text/csv',
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
                    $presentDays = $att->present_days ?? collect($daysMap)->only(['mon', 'tue', 'wed', 'thu', 'fri', 'sat'])->sum();
                    $absentDays = max(0, 6 - $presentDays);

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
                    $totalHours = 0;
                    $daysPresent = 0;

                    foreach ($dayKeys as $key) {
                        $hrs = (float) ($hoursMap[$key] ?? 0);
                        $row[] = $hrs > 0 ? number_format($hrs, 2).'h' : 'OFF';
                        $totalHours += $hrs;
                        if ($hrs > 0) {
                            $daysPresent++;
                        }
                    }
                    $row[] = "{$daysPresent} days / ".number_format($totalHours, 2).'h';
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
                'user_id' => auth()->id(),
                'action' => 'updated',
                'model_type' => 'PayrollRun',
                'model_id' => $runId,
                'old_values' => ['action_type' => $actionType],
                'new_values' => ['action_type' => $actionType, 'employees' => $diffs],
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            logger()->error('Payroll audit write failed: '.$e->getMessage());
        }
    }
}
