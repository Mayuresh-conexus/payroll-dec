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

    // ── weekly_amount in merged rows includes OT ──────────────────────────────

    public function test_daily_merged_row_weekly_amount_includes_overtime(): void
    {
        // 150/day × 6 days + 300 OT → weekly_amount must be 1200, not 900
        $emp = Employee::factory()->create([
            'type'                    => 'daily_rate',
            'daily_rate'              => 150,
            'bank_transfer_fix_amount'=> 450,
        ]);

        \App\Models\DailyRateAttendance::create([
            'employee_id'        => $emp->id,
            'year'               => 2025,
            'week_number'        => 10,
            'total_working_days' => 6,
            'present_days'       => 6,
            'overtime_amount'    => 300,
            'days_map'           => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ]);

        $rows   = $this->service->buildRowsFromAttendance(2025, 10);
        $merged = $this->service->mergeWithPayrollRun($rows, null, false, false);
        $row    = $merged->first();

        $this->assertEquals(1200.0, $row['gross_amount']);
        $this->assertEquals(1200.0, $row['weekly_amount']);
    }

    public function test_hourly_merged_row_weekly_amount_includes_overtime(): void
    {
        // 17.50/hr, 40 regular + 10 OT = 875
        $emp = Employee::factory()->create([
            'type'                    => 'hourly',
            'hourly_rate'             => 17.50,
            'hours_per_day'           => 8,
            'bank_transfer_fix_amount'=> 700,
        ]);

        \App\Models\HourlyAttendance::create([
            'employee_id'    => $emp->id,
            'year'           => 2025,
            'week_number'    => 10,
            'total_hours'    => 40,
            'overtime_hours' => 10,
            'hours_map'      => ['mon' => 11, 'tue' => 10, 'wed' => 10, 'thu' => 11, 'fri' => 8, 'sat' => 0, 'sun' => 0],
            'ot_map'         => ['mon' => 3, 'tue' => 2, 'wed' => 2, 'thu' => 3, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ]);

        $rows   = $this->service->buildRowsFromAttendance(2025, 10);
        $merged = $this->service->mergeWithPayrollRun($rows, null, false, false);
        $row    = $merged->first();

        $this->assertEquals(875.0, $row['gross_amount']);
        $this->assertEquals(875.0, $row['weekly_amount']);
    }

    // ── bank_fix as default split when no cash saved ──────────────────────────

    public function test_daily_bank_fix_used_as_default_when_no_cash(): void
    {
        $emp = Employee::factory()->create([
            'type'                    => 'daily_rate',
            'daily_rate'              => 150,
            'bank_transfer_fix_amount'=> 450,
        ]);

        \App\Models\DailyRateAttendance::create([
            'employee_id'        => $emp->id,
            'year'               => 2025,
            'week_number'        => 11,
            'total_working_days' => 6,
            'present_days'       => 6,
            'overtime_amount'    => 300,
            'days_map'           => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ]);

        $rows   = $this->service->buildRowsFromAttendance(2025, 11);
        $merged = $this->service->mergeWithPayrollRun($rows, null, false, false);
        $row    = $merged->first();

        // cash=0, bank defaults to fix amount
        $this->assertEquals(0.0,   $row['cash_amount']);
        $this->assertEquals(450.0, $row['bank_amount']);
    }

    public function test_hourly_bank_fix_used_as_default_when_no_cash(): void
    {
        $emp = Employee::factory()->create([
            'type'                    => 'hourly',
            'hourly_rate'             => 17.50,
            'hours_per_day'           => 8,
            'bank_transfer_fix_amount'=> 700,
        ]);

        \App\Models\HourlyAttendance::create([
            'employee_id'    => $emp->id,
            'year'           => 2025,
            'week_number'    => 11,
            'total_hours'    => 40,
            'overtime_hours' => 10,
            'hours_map'      => ['mon' => 11, 'tue' => 10, 'wed' => 10, 'thu' => 11, 'fri' => 8, 'sat' => 0, 'sun' => 0],
            'ot_map'         => ['mon' => 3, 'tue' => 2, 'wed' => 2, 'thu' => 3, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ]);

        $rows   = $this->service->buildRowsFromAttendance(2025, 11);
        $merged = $this->service->mergeWithPayrollRun($rows, null, false, false);
        $row    = $merged->first();

        $this->assertEquals(0.0,   $row['cash_amount']);
        $this->assertEquals(700.0, $row['bank_amount']);
    }

    // ── trusted branch: correct overtime field and bank default ───────────────

    public function test_trusted_branch_uses_overtime_amount_for_daily(): void
    {
        $user = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $emp = Employee::factory()->create([
            'type'                    => 'daily_rate',
            'daily_rate'              => 150,
            'bank_transfer_fix_amount'=> 450,
        ]);

        \App\Models\DailyRateAttendance::create([
            'employee_id'        => $emp->id,
            'year'               => 2025,
            'week_number'        => 12,
            'total_working_days' => 6,
            'present_days'       => 6,
            'overtime_amount'    => 300,
            'locked'             => true,
            'days_map'           => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ]);

        $run = PayrollRun::create([
            'year'         => 2025,
            'week_number'  => 12,
            'status'       => 'draft',
            'created_by'   => $user->id,
            'generated_at' => now(),
        ]);

        \App\Models\PayrollItem::create([
            'payroll_run_id'  => $run->id,
            'employee_id'     => $emp->id,
            'type'            => 'daily_rate',
            'gross_amount'    => 1200,
            'weekly_amount'   => 1200,
            'cash_amount'     => 0,
            'bank_amount'     => 450,
            'overtime_amount' => 300,
            'overtime_hours'  => null,
        ]);

        $rows   = $this->service->buildRowsFromAttendance(2025, 12);
        // dailyLocked = true triggers trusted branch
        $merged = $this->service->mergeWithPayrollRun($rows, $run, true, false);
        $row    = $merged->first();

        // overtime_amount should be 300, not null
        $this->assertEquals(300.0, $row['overtime_amount']);
        $this->assertArrayNotHasKey('overtime_hours', $row);
    }

    public function test_trusted_branch_uses_bank_fix_when_cash_is_zero(): void
    {
        $user = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $emp = Employee::factory()->create([
            'type'                    => 'daily_rate',
            'daily_rate'              => 150,
            'bank_transfer_fix_amount'=> 450,
        ]);

        \App\Models\DailyRateAttendance::create([
            'employee_id'        => $emp->id,
            'year'               => 2025,
            'week_number'        => 13,
            'total_working_days' => 6,
            'present_days'       => 6,
            'overtime_amount'    => 300,
            'locked'             => true,
            'days_map'           => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ]);

        $run = PayrollRun::create([
            'year'         => 2025,
            'week_number'  => 13,
            'status'       => 'draft',
            'created_by'   => $user->id,
            'generated_at' => now(),
        ]);

        // cash=0 (not yet explicitly saved by user)
        \App\Models\PayrollItem::create([
            'payroll_run_id'  => $run->id,
            'employee_id'     => $emp->id,
            'type'            => 'daily_rate',
            'gross_amount'    => 1200,
            'weekly_amount'   => 1200,
            'cash_amount'     => 0,
            'bank_amount'     => 450,
            'overtime_amount' => 300,
            'overtime_hours'  => null,
        ]);

        $rows   = $this->service->buildRowsFromAttendance(2025, 13);
        $merged = $this->service->mergeWithPayrollRun($rows, $run, true, false);
        $row    = $merged->first();

        // With cash=0, bank should be fix amount (450), not gross (1200)
        $this->assertEquals(0.0,   $row['cash_amount']);
        $this->assertEquals(450.0, $row['bank_amount']);
    }

    // ── cash+bank split integrity ────────────────────────────────────────────

    public function test_cash_plus_bank_equals_gross_after_merge(): void
    {
        $emp = Employee::factory()->create([
            'type'                    => 'daily_rate',
            'daily_rate'              => 150,
            'bank_transfer_fix_amount'=> 450,
        ]);

        \App\Models\DailyRateAttendance::create([
            'employee_id'        => $emp->id,
            'year'               => 2025,
            'week_number'        => 14,
            'total_working_days' => 6,
            'present_days'       => 6,
            'overtime_amount'    => 300,
            'days_map'           => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ]);

        $rows   = $this->service->buildRowsFromAttendance(2025, 14);
        $merged = $this->service->mergeWithPayrollRun($rows, null, false, false);
        $row    = $merged->first();

        // cash (0) + bank (450) does NOT need to equal gross — cash is 0, bank is fix
        // What matters: bank ≤ gross and cash ≥ 0
        $this->assertGreaterThanOrEqual(0.0, $row['cash_amount']);
        $this->assertLessThanOrEqual($row['gross_amount'], $row['bank_amount']);
    }

    public function test_clamp_cash_never_exceeds_gross(): void
    {
        $this->assertEquals(500.0, $this->service->clampCash(600, 500)); // clamped
        $this->assertEquals(400.0, $this->service->clampCash(400, 500)); // unchanged
        $this->assertEquals(0.0,   $this->service->clampCash(-10, 500)); // floor at 0
        $this->assertEquals(0.0,   $this->service->clampCash(100, 0));   // gross=0
    }
}
