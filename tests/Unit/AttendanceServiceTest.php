<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttendanceService::class);
    }

    // ── saveDailyEmployee ─────────────────────────────────────────────────────

    public function test_save_daily_employee_creates_attendance_record(): void
    {
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 450]);

        $row = [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ];

        $this->service->saveDailyEmployee($emp->id, $row, 2025, 30, false);

        $this->assertDatabaseHas('daily_rate_attendances', [
            'employee_id' => $emp->id,
            'week_number' => 30,
            'present_days' => 3,
        ]);
    }

    public function test_save_daily_employee_present_days_sum_is_correct(): void
    {
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500]);

        $row = [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ];

        $this->service->saveDailyEmployee($emp->id, $row, 2025, 31, false);

        $att = \App\Models\DailyRateAttendance::where('employee_id', $emp->id)
            ->where('week_number', 31)
            ->first();

        $this->assertEquals(6, $att->present_days);
    }

    public function test_overtime_amount_is_stored_and_included_in_gross(): void
    {
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500]);

        $row = [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
            'overtime_map' => ['mon' => 200, 'tue' => 0, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ];

        $this->service->saveDailyEmployee($emp->id, $row, 2025, 32, false);

        $this->assertDatabaseHas('daily_rate_attendances', [
            'employee_id' => $emp->id,
            'week_number' => 32,
            'overtime_amount' => 200,
        ]);

        // payroll item gross should include overtime
        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        // 2 days × 500 + 200 OT = 1200
        $this->assertEquals(1200.0, $item->gross_amount);
    }

    // ── saveHourlyEmployee ────────────────────────────────────────────────────

    public function test_save_hourly_employee_creates_attendance_record(): void
    {
        $emp = Employee::factory()->create([
            'type' => 'hourly',
            'hourly_rate' => 80,
            'hours_per_day' => 8,
        ]);

        $row = [
            'hours_map' => ['mon' => 8, 'tue' => 8, 'wed' => 8, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
            'ot_map' => [],
        ];

        $this->service->saveHourlyEmployee($emp->id, $row, 2025, 33, false);

        $this->assertDatabaseHas('hourly_attendances', [
            'employee_id' => $emp->id,
            'week_number' => 33,
            'total_hours' => 24,    // 3 days × 8 hours
        ]);
    }

    public function test_save_hourly_gross_equals_hours_times_rate(): void
    {
        $emp = Employee::factory()->create([
            'type' => 'hourly',
            'hourly_rate' => 100,
            'hours_per_day' => 8,
        ]);

        $row = [
            'hours_map' => ['mon' => 8, 'tue' => 8, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
            'ot_map' => [],
        ];

        $this->service->saveHourlyEmployee($emp->id, $row, 2025, 34, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(1600.0, $item->gross_amount); // 16h × 100
    }

    // ── weekly_amount = gross (includes OT) ───────────────────────────────────

    public function test_daily_weekly_amount_includes_overtime(): void
    {
        // Billal scenario: 150/day × 6 days + 300 OT = 1200
        $emp = Employee::factory()->create([
            'type' => 'daily_rate',
            'daily_rate' => 150,
            'bank_transfer_fix_amount' => 450,
        ]);

        $row = [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
            'overtime_map' => ['mon' => 60, 'tue' => 60, 'wed' => 60, 'thu' => 60, 'fri' => 60, 'sat' => 0, 'sun' => 0],
        ];

        $this->service->saveDailyEmployee($emp->id, $row, 2025, 10, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();

        $this->assertEquals(1200.0, $item->gross_amount);   // 900 base + 300 OT
        $this->assertEquals(1200.0, $item->weekly_amount);  // must match gross, not base-only 900
        $this->assertEquals(300.0, $item->overtime_amount);
    }

    public function test_hourly_weekly_amount_includes_overtime(): void
    {
        // Derek scenario: 17.50/hr, 40 regular hrs + 10 OT hrs = 875 total
        $emp = Employee::factory()->create([
            'type' => 'hourly',
            'hourly_rate' => 17.50,
            'hours_per_day' => 8,
            'bank_transfer_fix_amount' => 700,
        ]);

        // 11+10+10+11+8 = 50 total hrs; default 8/day × 5 = 40 regular; OT = 10
        $row = [
            'hours_map' => ['mon' => 11, 'tue' => 10, 'wed' => 10, 'thu' => 11, 'fri' => 8, 'sat' => 0, 'sun' => 0],
            'ot_map' => [],
        ];

        $this->service->saveHourlyEmployee($emp->id, $row, 2025, 10, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();

        $this->assertEquals(40.0, $item->total_hours);    // regular hours only
        $this->assertEquals(10.0, $item->overtime_hours); // auto-computed OT
        $this->assertEquals(875.0, $item->gross_amount);   // (40 + 10) × 17.50
        $this->assertEquals(875.0, $item->weekly_amount);  // must match gross, not base-only 700
    }

    public function test_hourly_ot_auto_calculated_from_excess_hours(): void
    {
        // When hours_map exceeds hours_per_day, the excess is auto-promoted to OT
        $emp = Employee::factory()->create([
            'type' => 'hourly',
            'hourly_rate' => 20,
            'hours_per_day' => 8,
        ]);

        $row = [
            'hours_map' => ['mon' => 10, 'tue' => 10, 'wed' => 8, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
            'ot_map' => [],
        ];

        $this->service->saveHourlyEmployee($emp->id, $row, 2025, 11, false);

        $att = \App\Models\HourlyAttendance::where('employee_id', $emp->id)->first();

        // Regular = 28 - 4 OT = 24; OT = 2+2 = 4
        $this->assertEquals(24.0, $att->total_hours);
        $this->assertEquals(4.0, $att->overtime_hours);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        // gross = 24 × 20 + 4 × 20 = 560
        $this->assertEquals(560.0, $item->gross_amount);
        $this->assertEquals(560.0, $item->weekly_amount);
    }

    // ── bank_fix_amount applied as default cash=0 ────────────────────────────

    public function test_daily_bank_fix_stored_when_no_cash_set(): void
    {
        $emp = Employee::factory()->create([
            'type' => 'daily_rate',
            'daily_rate' => 150,
            'bank_transfer_fix_amount' => 450,
        ]);

        $row = [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
            'overtime_map' => ['mon' => 60, 'tue' => 60, 'wed' => 60, 'thu' => 60, 'fri' => 60, 'sat' => 0, 'sun' => 0],
        ];

        $this->service->saveDailyEmployee($emp->id, $row, 2025, 12, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(0.0, $item->cash_amount);
        $this->assertEquals(450.0, $item->bank_amount); // fix amount, not gross
    }

    public function test_hourly_bank_fix_stored_when_no_cash_set(): void
    {
        $emp = Employee::factory()->create([
            'type' => 'hourly',
            'hourly_rate' => 17.50,
            'hours_per_day' => 8,
            'bank_transfer_fix_amount' => 700,
        ]);

        $row = [
            'hours_map' => ['mon' => 11, 'tue' => 10, 'wed' => 10, 'thu' => 11, 'fri' => 8, 'sat' => 0, 'sun' => 0],
            'ot_map' => [],
        ];

        $this->service->saveHourlyEmployee($emp->id, $row, 2025, 12, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(0.0, $item->cash_amount);
        $this->assertEquals(700.0, $item->bank_amount); // fix amount, not gross
    }

    // ── zero OT scenarios ────────────────────────────────────────────────────

    public function test_daily_no_ot_weekly_amount_equals_base_pay(): void
    {
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 200]);

        $row = [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
        ];

        $this->service->saveDailyEmployee($emp->id, $row, 2025, 20, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(1000.0, $item->gross_amount);
        $this->assertEquals(1000.0, $item->weekly_amount);
        $this->assertEquals(0.0, $item->overtime_amount);
    }

    public function test_hourly_no_ot_weekly_amount_equals_regular_pay(): void
    {
        $emp = Employee::factory()->create([
            'type' => 'hourly',
            'hourly_rate' => 17.50,
            'hours_per_day' => 8,
        ]);

        $row = [
            'hours_map' => ['mon' => 8, 'tue' => 8, 'wed' => 8, 'thu' => 8, 'fri' => 8, 'sat' => 0, 'sun' => 0],
            'ot_map' => [],
        ];

        $this->service->saveHourlyEmployee($emp->id, $row, 2025, 20, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        // 40 × 17.50 = 700, zero OT
        $this->assertEquals(700.0, $item->gross_amount);
        $this->assertEquals(700.0, $item->weekly_amount);
        $this->assertEquals(0.0, $item->overtime_hours);
    }

    // ── partial attendance ────────────────────────────────────────────────────

    public function test_daily_partial_attendance_with_ot(): void
    {
        // 3 days present at 200/day + 150 OT = 750 total
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 200]);

        $row = [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
            'overtime_map' => ['mon' => 50, 'tue' => 50, 'wed' => 50, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ];

        $this->service->saveDailyEmployee($emp->id, $row, 2025, 21, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(750.0, $item->gross_amount);
        $this->assertEquals(750.0, $item->weekly_amount);
        $this->assertEquals(150.0, $item->overtime_amount);
    }

    public function test_hourly_absent_employee_gets_zero(): void
    {
        $emp = Employee::factory()->create([
            'type' => 'hourly',
            'hourly_rate' => 17.50,
            'hours_per_day' => 8,
        ]);

        $row = [
            'hours_map' => ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
            'ot_map' => [],
        ];

        $this->service->saveHourlyEmployee($emp->id, $row, 2025, 22, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(0.0, $item->gross_amount);
        $this->assertEquals(0.0, $item->weekly_amount);
        $this->assertEquals(0.0, $item->overtime_hours);
    }

    // ── Advance balance carries forward via attendance saves ──────────────────

    public function test_advance_balance_carries_to_next_week_via_attendance(): void
    {
        // Week 10: earn 1120 daily, bank_fix 2000 → advance 880 stored in PayrollItem
        $emp = Employee::factory()->create([
            'type' => 'daily_rate',
            'daily_rate' => 200,
            'bank_transfer_fix_amount' => 2000,
        ]);

        $week10Row = [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
            'overtime_map' => ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 120, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ];

        $this->service->saveDailyEmployee($emp->id, $week10Row, 2025, 10, false);

        $item10 = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        // gross = 5×200 + 120 OT = 1120; bank_fix = 2000; advance = 2000 - 1120 = 880
        $this->assertEquals(880.0, (float) $item10->advance_balance);
        $this->assertEquals(880.0, (float) $item10->advance_given);

        // Week 11: earn 2615 (> bank_fix 2000) → advance auto-recovers via formula
        // gross = 5×200 base + 1615 OT = 2615
        $week11Row = [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
            'overtime_map' => ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 1615, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ];

        $this->service->saveDailyEmployee($emp->id, $week11Row, 2025, 11, false);

        $item11 = \App\Models\PayrollItem::where('employee_id', $emp->id)
            ->orderBy('id', 'desc')->first();

        // AttendanceService never auto-recovers. Only saveWeek can record recovery.
        // prev=880, given=0 (gross 2615 > bank 2000), recovered=0 → balance stays 880
        $this->assertEquals(880.0, (float) $item11->advance_balance);
        $this->assertEquals(0.0, (float) $item11->advance_recovered);
        $this->assertEquals(0.0, (float) $item11->advance_given);
    }

    public function test_prev_advance_balance_zero_when_no_prior_week(): void
    {
        $emp = Employee::factory()->create([
            'type' => 'daily_rate',
            'daily_rate' => 200,
            'bank_transfer_fix_amount' => 1000,
        ]);

        $row = [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ];

        $this->service->saveDailyEmployee($emp->id, $row, 2025, 5, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        // 6×200 = 1200 > bank_fix 1000 → no advance
        $this->assertEquals(0.0, (float) $item->advance_balance);
        $this->assertEquals(0.0, (float) $item->advance_given);
    }

    // ── Cross-month carry-forward (S1) ────────────────────────────────────────
    // Calendar: W22 2025 = May 26, W23 2025 = June 2, W24 2025 = June 9

    public function test_advance_in_last_week_of_month_carries_to_first_week_of_next_month(): void
    {
        // W22 (May 26): bank_fix 2000, earned 1000 → advance_given 1000
        // W23 (June 2): earned 2200 (> bank_fix 2000) → advance_given 0, but carry-over visible
        $emp = Employee::factory()->create([
            'type' => 'daily_rate',
            'daily_rate' => 200,
            'bank_transfer_fix_amount' => 2000,
        ]);

        // W22 May: 5 days × 200 = 1000 earned, bank_fix 2000 → advance 1000
        $this->service->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
        ], 2025, 22, false);

        $item22 = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(1000.0, (float) $item22->advance_balance, 'W22 advance saved');

        // W23 June (first week of new month): 5 days + 1200 OT = 2200 > 2000 → no new advance
        // carry-over 1000 should propagate → advance_balance = max(0, 1000 + 0 - 0) = 1000
        $this->service->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
            'overtime_map' => ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 1200, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ], 2025, 23, false);

        $item23 = \App\Models\PayrollItem::where('employee_id', $emp->id)->orderBy('id', 'desc')->first();
        $this->assertEquals(0.0, (float) $item23->advance_given, 'No new advance in June W23');
        $this->assertEquals(1000.0, (float) $item23->advance_balance, 'Carry-over from May W22 visible in June W23');
    }

    public function test_carry_forward_propagates_through_subsequent_weeks_in_new_month(): void
    {
        // W22 May → W23 June (no recovery) → W24 June should still see carry-over
        $emp = Employee::factory()->create([
            'type' => 'daily_rate',
            'daily_rate' => 200,
            'bank_transfer_fix_amount' => 2000,
        ]);

        $this->service->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
        ], 2025, 22, false);  // advance_balance = 1000

        $this->service->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
            'overtime_map' => ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 1200, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ], 2025, 23, false);  // no recovery → advance_balance stays 1000

        $this->service->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
            'overtime_map' => ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 1200, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ], 2025, 24, false);  // W24 (June 9): still carries 1000

        $item24 = \App\Models\PayrollItem::where('employee_id', $emp->id)->orderBy('id', 'desc')->first();
        $this->assertEquals(1000.0, (float) $item24->advance_balance, 'Carry-over visible in W24 (2nd week of June)');
    }

    public function test_advance_from_earlier_in_previous_month_is_not_carried_forward(): void
    {
        // W20 (May 12) and W21 (May 19) are both before lookbackFrom=May 26 → excluded
        // W23 (June) should see balance = 0 (those weeks are out of window)
        $emp = Employee::factory()->create([
            'type' => 'daily_rate',
            'daily_rate' => 200,
            'bank_transfer_fix_amount' => 2000,
        ]);

        // W20 and W21 (May 12 and 19): advances given, before the carry-over window
        foreach ([20, 21] as $w) {
            $this->service->saveDailyEmployee($emp->id, [
                'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
            ], 2025, $w, false);  // each gives advance 1000
        }

        // W23 (June 2): lookbackFrom = May 26. W20/W21 (May 12/19) are excluded.
        // No W22 advance → prev_balance for W23 should be 0
        $this->service->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
            'overtime_map' => ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 1200, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ], 2025, 23, false);

        $item23 = \App\Models\PayrollItem::where('employee_id', $emp->id)->orderBy('id', 'desc')->first();
        $this->assertEquals(0.0, (float) $item23->advance_balance, 'Advances from W20/W21 (before lookback window) are not carried to June');
        $this->assertEquals(0.0, (float) $item23->advance_given, 'No new advance in W23 (earned 2200 > bank_fix 2000)');
    }

    public function test_settlement_in_last_week_reduces_carry_forward(): void
    {
        // W22 (May 26): advance 1000, then explicit recovery 600 in same week via saveWeek
        // W23 (June): carry-over should be 400, not 1000
        $user = \App\Models\User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        $emp = Employee::factory()->create([
            'type' => 'daily_rate',
            'daily_rate' => 200,
            'bank_transfer_fix_amount' => 2000,
        ]);

        // First, save attendance for W22 (creates advance 1000)
        $this->service->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
        ], 2025, 22, false);

        // Then admin explicitly recovers 600 via saveWeek (sets advance_recovered = 600)
        $run = \App\Models\PayrollRun::where('year', 2025)->where('week_number', 22)->first();
        \App\Models\PayrollItem::where('payroll_run_id', $run->id)
            ->where('employee_id', $emp->id)
            ->update(['advance_recovered' => 600, 'advance_balance' => 400]);

        // W23 June: carry-over should be 400 (not 1000)
        $this->service->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
            'overtime_map' => ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 1200, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ], 2025, 23, false);

        $item23 = \App\Models\PayrollItem::where('employee_id', $emp->id)->orderBy('id', 'desc')->first();
        $this->assertEquals(400.0, (float) $item23->advance_balance, 'Carry-over reflects partial settlement from W22');
    }

    public function test_zero_earnings_full_bank_creates_advance_equal_to_bank_fix(): void
    {
        // Employee absent all week (unpaid): earned = 0, bank_fix sent as advance
        $emp = Employee::factory()->create([
            'type' => 'daily_rate',
            'daily_rate' => 200,
            'bank_transfer_fix_amount' => 700,
        ]);

        $this->service->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ], 2025, 10, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(0.0, (float) $item->weekly_amount, 'Zero earnings');
        $this->assertEquals(700.0, (float) $item->bank_amount, 'Bank = bank_fix even with no earnings');
        $this->assertEquals(700.0, (float) $item->advance_given, 'Full bank_fix is an advance');
        $this->assertEquals(700.0, (float) $item->advance_balance, 'Balance = full advance');
    }

    // ── Overtime is derived, not accumulated ─────────────────────────────────

    public function test_hourly_overtime_is_the_hours_above_the_daily_norm(): void
    {
        // 8 h/day: a 10 h day is 8 regular + 2 OT, an 11 h day is 8 + 3.
        $emp = Employee::factory()->create([
            'type' => 'hourly', 'hourly_rate' => 17.50, 'hours_per_day' => 8,
        ]);

        $this->service->saveHourlyEmployee($emp->id, [
            'hours_map' => ['mon' => 10, 'tue' => 10, 'wed' => 11, 'thu' => 11, 'fri' => 8],
        ], 2026, 31, false);

        $att = \App\Models\HourlyAttendance::where('employee_id', $emp->id)->where('week_number', 31)->first();

        $this->assertEquals([2, 2, 3, 3, 0], [
            $att->ot_map['mon'], $att->ot_map['tue'], $att->ot_map['wed'],
            $att->ot_map['thu'], $att->ot_map['fri'],
        ]);
        $this->assertEqualsWithDelta(10.0, $att->overtime_hours, 0.001);
        $this->assertEqualsWithDelta(40.0, $att->total_hours, 0.001, 'regular hours = 50 worked - 10 OT');
    }

    public function test_resaving_the_same_week_does_not_grow_overtime(): void
    {
        // Regression guard: payroll's "refresh week" re-saves attendance and passes
        // the stored ot_map straight back in. When OT was added to the derived
        // amount instead of derived from hours, every refresh doubled it up —
        // a 10 h day drifted 2 -> 4 -> 6 OT while the hours themselves never moved.
        $emp = Employee::factory()->create([
            'type' => 'hourly', 'hourly_rate' => 17.50, 'hours_per_day' => 8,
        ]);

        $hours = ['mon' => 10, 'tue' => 10, 'wed' => 11, 'thu' => 11, 'fri' => 8];
        $this->service->saveHourlyEmployee($emp->id, ['hours_map' => $hours], 2026, 31, false);

        for ($i = 0; $i < 3; $i++) {
            $att = \App\Models\HourlyAttendance::where('employee_id', $emp->id)->where('week_number', 31)->first();

            $this->service->saveHourlyEmployee($emp->id, [
                'hours_map' => $att->hours_map,
                'ot_map' => $att->ot_map,
                'days' => [],
            ], 2026, 31, false);
        }

        $att = \App\Models\HourlyAttendance::where('employee_id', $emp->id)->where('week_number', 31)->first();

        $this->assertEqualsWithDelta(10.0, $att->overtime_hours, 0.001, 'OT must not compound across refreshes');
        $this->assertEqualsWithDelta(40.0, $att->total_hours, 0.001);
        $this->assertEqualsWithDelta(2.0, $att->ot_map['mon'], 0.001);
        $this->assertEqualsWithDelta(3.0, $att->ot_map['wed'], 0.001);
    }

    // ── Bank holidays ────────────────────────────────────────────────────────

    public function test_working_a_bank_holiday_pays_double_and_splits_the_extra(): void
    {
        // 135/day with 40% of the premium to bank: 135 base + 135 extra = 270.
        // Only the 81 cash share is weekly earnings — the 54 bank share leaves as
        // its own transfer, so weekly_amount is 135 + 81 = 216.
        // Week 31 of 2026 holds 31 July, so it settles that month.
        \App\Models\Holiday::factory()->on('2026-07-27')->create(['name' => 'August BH']);

        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135,
            'bh_bank_percent' => 40, 'bank_transfer_fix_amount' => 0,
        ]);

        $this->service->saveDailyEmployee($emp->id, ['days' => ['mon' => 1]], 2026, 31, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->firstOrFail();

        $this->assertEqualsWithDelta(216.0, (float) $item->weekly_amount, 0.001, 'base + cash share only');
        $this->assertEqualsWithDelta(135.0, (float) $item->bh_amount, 0.001);
        $this->assertEqualsWithDelta(81.0, (float) $item->bh_cash, 0.001);
        $this->assertEqualsWithDelta(54.0, (float) $item->bh_bank, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $item->bank_amount, 0.001, 'bank stays the fixed amount');
        $this->assertEqualsWithDelta(40.0, (float) $item->applied_bh_bank_percent, 0.001);

        // Double pay still holds across the two channels: 216 + 54 = 270.
        $this->assertEqualsWithDelta(270.0, (float) $item->weekly_amount + (float) $item->bh_bank, 0.001);
    }

    public function test_a_bank_holiday_that_is_not_worked_pays_nothing_extra(): void
    {
        \App\Models\Holiday::factory()->on('2026-07-27')->create();

        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135, 'bh_bank_percent' => 40,
        ]);

        // Present Tue and Wed, absent on the holiday itself.
        $this->service->saveDailyEmployee($emp->id, ['days' => ['tue' => 1, 'wed' => 1]], 2026, 31, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->firstOrFail();

        $this->assertEqualsWithDelta(270.0, (float) $item->weekly_amount, 0.001, '2 ordinary days only');
        $this->assertEqualsWithDelta(0.0, (float) $item->bh_amount, 0.001);
    }

    public function test_hourly_bank_holiday_doubles_every_hour_including_overtime(): void
    {
        // 10 h at 17.50 on an 8 h norm: 140 regular + 35 overtime = 175 normally,
        // and the premium doubles all ten hours, so the day is worth 350.
        \App\Models\Holiday::factory()->on('2026-07-27')->create();

        $emp = Employee::factory()->create([
            'type' => 'hourly', 'hourly_rate' => 17.50, 'hours_per_day' => 8,
            'bh_bank_percent' => 0,
        ]);

        $this->service->saveHourlyEmployee($emp->id, ['hours_map' => ['mon' => 10]], 2026, 31, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->firstOrFail();

        $this->assertEqualsWithDelta(350.0, (float) $item->weekly_amount, 0.001);
        $this->assertEqualsWithDelta(175.0, (float) $item->bh_amount, 0.001);
        $this->assertEqualsWithDelta(175.0, (float) $item->bh_cash, 0.001, '0% to bank means all cash');
        $this->assertEqualsWithDelta(0.0, (float) $item->bh_bank, 0.001);
    }

    public function test_a_bank_holiday_after_the_leaving_date_pays_nothing(): void
    {
        \App\Models\Holiday::factory()->on('2026-07-29')->create(); // Wednesday

        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135, 'bh_bank_percent' => 40,
            'is_active' => false, 'deactivated_at' => '2026-07-28', // left on the Tuesday
        ]);

        $this->service->saveDailyEmployee($emp->id, ['days' => ['mon' => 1, 'wed' => 1]], 2026, 31, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->firstOrFail();

        $this->assertEqualsWithDelta(0.0, (float) $item->bh_amount, 0.001);
        $this->assertEqualsWithDelta(135.0, (float) $item->weekly_amount, 0.001, 'only the Monday is payable');
    }

    public function test_resaving_the_same_week_does_not_grow_the_bank_holiday_premium(): void
    {
        // Same guard as overtime: payroll's refresh re-saves attendance, and the
        // premium must be derived each time rather than accumulated.
        \App\Models\Holiday::factory()->on('2026-07-27')->create();

        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135, 'bh_bank_percent' => 40,
        ]);

        for ($i = 0; $i < 4; $i++) {
            $this->service->saveDailyEmployee($emp->id, ['days' => ['mon' => 1]], 2026, 31, false);
        }

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->firstOrFail();

        $this->assertEqualsWithDelta(135.0, (float) $item->bh_amount, 0.001);
        $this->assertEqualsWithDelta(216.0, (float) $item->weekly_amount, 0.001);
    }

    public function test_a_bank_holiday_week_does_not_create_a_phantom_advance(): void
    {
        // advance_given is derived as bank - weekly in several places. The premium
        // lives inside weekly_amount, so a BH week must not look like an advance.
        \App\Models\Holiday::factory()->on('2026-07-27')->create();

        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135,
            'bh_bank_percent' => 100, 'bank_transfer_fix_amount' => 200,
        ]);

        $this->service->saveDailyEmployee($emp->id, ['days' => ['mon' => 1, 'tue' => 1]], 2026, 31, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->firstOrFail();

        // 100% of the premium goes to bank, so weekly earnings are just the two
        // days (270) and the bank transfer stays at the fixed 200.
        $this->assertEqualsWithDelta(270.0, (float) $item->weekly_amount, 0.001);
        $this->assertEqualsWithDelta(200.0, (float) $item->bank_amount, 0.001);
        $this->assertEqualsWithDelta(135.0, (float) $item->bh_bank, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $item->advance_given, 0.001);
        $this->assertEqualsWithDelta(0.0, (float) $item->advance_balance, 0.001);
    }

    public function test_a_holiday_is_not_paid_in_its_own_week_but_in_the_month_settlement_week(): void
    {
        // Week 28 of 2026 (6-12 July) contains the holiday; week 31 holds 31 July
        // and is where the month clears.
        \App\Models\Holiday::factory()->on('2026-07-08')->create();

        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135, 'bh_bank_percent' => 40,
        ]);

        $this->service->saveDailyEmployee($emp->id, ['days' => ['wed' => 1]], 2026, 28, false);
        $this->service->saveDailyEmployee($emp->id, ['days' => ['mon' => 1]], 2026, 31, false);

        $holidayWeek = \App\Models\PayrollItem::whereRelation('run', 'week_number', 28)
            ->where('employee_id', $emp->id)->firstOrFail();
        $settlementWeek = \App\Models\PayrollItem::whereRelation('run', 'week_number', 31)
            ->where('employee_id', $emp->id)->firstOrFail();

        $this->assertEqualsWithDelta(0.0, (float) $holidayWeek->bh_amount, 0.001, 'nothing settles in the holiday week');
        $this->assertEqualsWithDelta(135.0, (float) $holidayWeek->weekly_amount, 0.001, 'just the day worked');

        $this->assertEqualsWithDelta(135.0, (float) $settlementWeek->bh_amount, 0.001, 'the month clears here');
        $this->assertEqualsWithDelta(81.0, (float) $settlementWeek->bh_cash, 0.001);
        $this->assertEqualsWithDelta(216.0, (float) $settlementWeek->weekly_amount, 0.001);
    }

    public function test_every_holiday_in_the_month_accumulates_into_one_settlement(): void
    {
        \App\Models\Holiday::factory()->on('2026-07-08')->create();
        \App\Models\Holiday::factory()->on('2026-07-15')->create();

        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135, 'bh_bank_percent' => 40,
        ]);

        $this->service->saveDailyEmployee($emp->id, ['days' => ['wed' => 1]], 2026, 28, false);
        $this->service->saveDailyEmployee($emp->id, ['days' => ['wed' => 1]], 2026, 29, false);
        $this->service->saveDailyEmployee($emp->id, ['days' => ['mon' => 1]], 2026, 31, false);

        $item = \App\Models\PayrollItem::whereRelation('run', 'week_number', 31)
            ->where('employee_id', $emp->id)->firstOrFail();

        $this->assertEqualsWithDelta(270.0, (float) $item->bh_amount, 0.001, 'two holidays worked');
        $this->assertEqualsWithDelta(162.0, (float) $item->bh_cash, 0.001);
        $this->assertEqualsWithDelta(108.0, (float) $item->bh_bank, 0.001);
    }

    public function test_a_holiday_falling_in_the_next_month_is_not_settled_early(): void
    {
        // Week 31 spans 27 Jul - 2 Aug. A holiday on 1 August belongs to August,
        // so July's settlement in that same week must ignore it.
        \App\Models\Holiday::factory()->on('2026-08-01')->create();

        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135, 'bh_bank_percent' => 40,
        ]);

        $this->service->saveDailyEmployee($emp->id, ['days' => ['sat' => 1]], 2026, 31, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->firstOrFail();

        $this->assertEqualsWithDelta(0.0, (float) $item->bh_amount, 0.001);
    }
}
