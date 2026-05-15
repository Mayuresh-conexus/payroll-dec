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
}
