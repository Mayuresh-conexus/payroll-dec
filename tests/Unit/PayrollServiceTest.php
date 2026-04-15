<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Services\PayrollService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollServiceTest extends TestCase
{
    use RefreshDatabase;

    private PayrollService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PayrollService::class);
    }

    // ── buildRowsFromAttendance ───────────────────────────────────────────────

    public function test_builds_empty_rows_when_no_attendance(): void
    {
        $rows = $this->service->buildRowsFromAttendance(2025, 1);

        $this->assertCount(0, $rows);
    }

    // ── mergeWithPayrollRun ───────────────────────────────────────────────────

    public function test_merge_returns_rows_with_correct_gross(): void
    {
        $emp = Employee::factory()->create([
            'type'       => 'daily_rate',
            'daily_rate' => 500,
        ]);

        // Create attendance directly
        \App\Models\DailyRateAttendance::create([
            'employee_id'        => $emp->id,
            'year'               => 2025,
            'week_number'        => 20,
            'total_working_days' => 6,
            'present_days'       => 5,
            'days_map'           => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
        ]);

        $rows = $this->service->buildRowsFromAttendance(2025, 20);

        $this->assertCount(1, $rows);
        $this->assertEquals(2500.0, $rows->first()['gross_amount']); // 5 × 500
    }

    public function test_merge_no_run_sets_cash_to_zero(): void
    {
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 400]);

        \App\Models\DailyRateAttendance::create([
            'employee_id'        => $emp->id,
            'year'               => 2025,
            'week_number'        => 21,
            'total_working_days' => 6,
            'present_days'       => 4,
            'days_map'           => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ]);

        $rows = $this->service->buildRowsFromAttendance(2025, 21);
        $merged = $this->service->mergeWithPayrollRun($rows, null, false, false);

        $this->assertEquals(0.0, $merged->first()['cash_amount']);
    }

    public function test_bank_never_exceeds_gross(): void
    {
        $user = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500]);

        \App\Models\DailyRateAttendance::create([
            'employee_id'        => $emp->id,
            'year'               => 2025,
            'week_number'        => 22,
            'total_working_days' => 6,
            'present_days'       => 3,
            'days_map'           => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ]);

        $run = PayrollRun::create([
            'year'         => 2025,
            'week_number'  => 22,
            'status'       => 'draft',
            'created_by'   => $user->id,
            'generated_at' => now(),
        ]);

        \App\Models\PayrollItem::create([
            'payroll_run_id' => $run->id,
            'employee_id'    => $emp->id,
            'type'           => 'daily_rate',
            'gross_amount'   => 1500,
            'cash_amount'    => 800,
            'bank_amount'    => 700,   // cash(800) + bank(700) = 1500 == gross → valid
            'weekly_amount'  => 1500,
        ]);

        $rows   = $this->service->buildRowsFromAttendance(2025, 22);
        $merged = $this->service->mergeWithPayrollRun($rows, $run, false, false);
        $row    = $merged->first();

        $this->assertLessThanOrEqual($row['gross_amount'], $row['bank_amount']);
    }
}
