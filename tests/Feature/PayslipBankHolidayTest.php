<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Holiday;
use App\Models\PayrollItem;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipBankHolidayTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /**
     * Render the payslip Blade directly. The route returns a PDF, whose text is
     * font-subset encoded and not readable, so the markup is asserted instead.
     */
    private function renderPayslip(Employee $employee, int $year, int $week): string
    {
        $item = PayrollItem::where('employee_id', $employee->id)->firstOrFail();
        $weekStart = Carbon::now()->setISODate($year, $week, 1);

        return view('payroll.payslip', [
            'year' => $year,
            'week' => $week,
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->addDays(6),
            'run' => $item->run,
            'item' => $item,
            'employee' => $employee,
            'dailyAtt' => null,
            'hourlyAtt' => null,
            'generatedAt' => now(),
        ])->render();
    }

    public function test_payslip_shows_the_bank_holiday_premium_and_the_lines_add_up(): void
    {
        // The breakdown must reconcile: base + overtime + premium = total gross.
        Holiday::factory()->on('2026-07-27')->create();

        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135, 'bh_bank_percent' => 40,
        ]);

        app(AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ], 2026, 31, false);

        $html = $this->renderPayslip($emp, 2026, 31);

        $this->assertStringContainsString('Bank holiday premium', $html);
        $this->assertStringContainsString('810.00', $html, 'base = 6 days x 135');
        $this->assertStringContainsString('81.00', $html, 'cash share of the 135 premium');
        $this->assertStringContainsString('891.00', $html, 'total gross = 810 + 81');
        $this->assertStringContainsString('54.00', $html, 'bank share, shown as its own transfer');
        $this->assertStringContainsString('945.00', $html, 'total paid = 891 + 54');

        $item = PayrollItem::where('employee_id', $emp->id)->firstOrFail();
        $base = (float) $item->present_days * (float) $item->applied_daily_rate;

        $this->assertEqualsWithDelta(
            (float) $item->gross_amount,
            $base + (float) $item->overtime_amount + (float) $item->bh_cash,
            0.01,
            'the printed lines must sum to the printed total'
        );
    }

    public function test_payslip_omits_the_premium_line_when_no_holiday_was_worked(): void
    {
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 135]);

        app(AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['tue' => 1, 'wed' => 1],
        ], 2026, 31, false);

        $this->assertStringNotContainsString('Bank holiday premium', $this->renderPayslip($emp, 2026, 31));
    }

    public function test_hourly_payslip_lines_add_up_with_overtime_and_a_holiday(): void
    {
        Holiday::factory()->on('2026-07-27')->create();

        $emp = Employee::factory()->create([
            'type' => 'hourly', 'hourly_rate' => 17.50, 'hours_per_day' => 8, 'bh_bank_percent' => 40,
        ]);

        app(AttendanceService::class)->saveHourlyEmployee($emp->id, [
            'hours_map' => ['mon' => 10],
        ], 2026, 31, false);

        $html = $this->renderPayslip($emp, 2026, 31);
        $item = PayrollItem::where('employee_id', $emp->id)->firstOrFail();

        $rate = (float) $item->applied_hourly_rate;
        $base = (float) $item->total_hours * $rate;
        $overtime = (float) $item->overtime_hours * $rate;

        $this->assertStringContainsString('Bank holiday premium', $html);
        $this->assertEqualsWithDelta(
            (float) $item->gross_amount,
            $base + $overtime + (float) $item->bh_cash,
            0.01
        );
        // 140 regular + 35 overtime + 105 cash share = 280; the 70 bank share
        // transfers separately, so the day is still worth 350 in total.
        $this->assertEqualsWithDelta(280.0, (float) $item->gross_amount, 0.01);
        $this->assertEqualsWithDelta(350.0, (float) $item->gross_amount + (float) $item->bh_bank, 0.01);
    }
}
