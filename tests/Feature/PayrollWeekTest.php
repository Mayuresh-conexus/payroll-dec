<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayrollWeekTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // ── Save week ─────────────────────────────────────────────────────────────

    public function test_save_week_creates_payroll_run_with_draft_status(): void
    {
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500]);

        $this->post('/payroll/save-week', [
            'year' => 2025,
            'week' => 10,
            'items' => [
                [
                    'employee_id' => $emp->id,
                    'type' => 'daily_rate',
                    'total_days' => 6,
                    'present_days' => 5,
                    'weekly_amount' => 2500,
                    'cash' => 1000,
                    'bank' => 1500,
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('payroll_runs', [
            'year' => 2025,
            'week_number' => 10,
            'status' => 'draft',
        ]);
    }

    public function test_save_week_creates_payroll_item(): void
    {
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500]);

        $this->post('/payroll/save-week', [
            'year' => 2025,
            'week' => 11,
            'items' => [
                [
                    'employee_id' => $emp->id,
                    'type' => 'daily_rate',
                    'total_days' => 6,
                    'present_days' => 6,
                    'weekly_amount' => 3000,
                    'cash' => 1500,
                    'bank' => 1500,
                ],
            ],
        ]);

        $this->assertDatabaseHas('payroll_items', [
            'employee_id' => $emp->id,
            'type' => 'daily_rate',
            'present_days' => 6,
            'weekly_amount' => 3000,
            'cash_amount' => 1500,
            'bank_amount' => 1500,
        ]);
    }

    public function test_cash_plus_bank_clamped_to_weekly_amount(): void
    {
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate']);

        $this->post('/payroll/save-week', [
            'year' => 2025,
            'week' => 12,
            'items' => [
                [
                    'employee_id' => $emp->id,
                    'type' => 'daily_rate',
                    'weekly_amount' => 1000,
                    'cash' => 600,
                    'bank' => 600,   // 600 + 600 = 1200 > 1000 → bank should be clamped to 400
                ],
            ],
        ]);

        $item = PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(600, $item->cash_amount);
        $this->assertEquals(400, $item->bank_amount);   // clamped
    }

    // ── Finalize week ─────────────────────────────────────────────────────────

    public function test_finalize_week_sets_status_to_final(): void
    {
        $this->actingAs($this->admin());

        $run = PayrollRun::create([
            'year' => 2025,
            'week_number' => 15,
            'status' => 'draft',
            'created_by' => auth()->id(),
            'generated_at' => now(),
        ]);

        $this->post('/payroll/finalize-week', [
            'year' => 2025,
            'week' => 15,
        ])->assertRedirect();

        $this->assertDatabaseHas('payroll_runs', [
            'id' => $run->id,
            'status' => 'final',
        ]);
    }

    public function test_finalize_returns_error_when_no_run_exists(): void
    {
        $this->actingAs($this->admin());

        $this->post('/payroll/finalize-week', [
            'year' => 2025,
            'week' => 99,
        ])->assertSessionHasErrors();
    }

    // ── Index view ────────────────────────────────────────────────────────────

    public function test_payroll_index_loads_for_admin(): void
    {
        $this->actingAs($this->admin());

        $this->get('/payroll?year=2025&week=1')->assertOk();
    }

    // ── Advance / Running Balance ─────────────────────────────────────────────

    public function test_advance_given_when_bank_exceeds_earnings(): void
    {
        // Employee earns €400 but bank_fix = €700 → advance = €300
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 100]);

        $this->post('/payroll/save-week', [
            'year' => 2025,
            'week' => 10,
            'items' => [[
                'employee_id' => $emp->id,
                'type' => 'daily_rate',
                'weekly_amount' => 400,
                'cash' => 0,
                'bank' => 700,   // exceeds 400 earnings
                'prev_advance_balance' => 0,
            ]],
        ]);

        $item = PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(300.00, (float) $item->advance_given);
        $this->assertEquals(0.00, (float) $item->advance_recovered);
        $this->assertEquals(300.00, (float) $item->advance_balance);
        $this->assertEquals(0.00, (float) $item->cash_amount);   // forced to 0 on advance
    }

    public function test_advance_balance_accumulates_across_weeks(): void
    {
        // Week 10: earn 400, bank 700 → balance = 300
        // Week 11: earn 900, bank 700 → partial recovery, balance = 100
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 100]);

        $this->post('/payroll/save-week', [
            'year' => 2025, 'week' => 10,
            'items' => [[
                'employee_id' => $emp->id, 'type' => 'daily_rate',
                'weekly_amount' => 400, 'cash' => 0, 'bank' => 700,
                'prev_advance_balance' => 0,
            ]],
        ]);

        $item10 = PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(300.00, (float) $item10->advance_balance);

        // Week 11: earn 900, bank_fix 700, explicit recover 200 from cash
        // cash = 900 - 700 - 200 = 0, balance = 300 - 200 = 100
        $this->post('/payroll/save-week', [
            'year' => 2025, 'week' => 11,
            'items' => [[
                'employee_id' => $emp->id, 'type' => 'daily_rate',
                'weekly_amount' => 900,
                'cash' => 0,    // cash = earned - bank_fix - recover
                'bank' => 700,  // bank stays at bank_fix
                'bank_transfer_fix_amount' => 700,
                'prev_advance_balance' => 300,
                'recover' => 200,  // explicit: deduct 200 from cash to recover advance
            ]],
        ]);

        $item11 = PayrollItem::where('employee_id', $emp->id)->orderBy('id', 'desc')->first();
        // balance = max(0, 300 + given(0) - recovered(200)) = 100
        $this->assertEquals(100.00, (float) $item11->advance_balance);
        $this->assertEquals(0.00, (float) $item11->advance_given);
        $this->assertEquals(200.00, (float) $item11->advance_recovered);
    }

    public function test_advance_fully_settled_when_bank_below_earnings(): void
    {
        // Prev balance 300. Earn 1000 with bank_fix 700 → surplus 300 = full recovery possible
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 100]);

        $this->post('/payroll/save-week', [
            'year' => 2025, 'week' => 11,
            'items' => [[
                'employee_id' => $emp->id, 'type' => 'daily_rate',
                'weekly_amount' => 1000,
                'cash' => 0,    // cash = 1000 - 700 - 300 = 0
                'bank' => 700,  // bank stays at bank_fix
                'bank_transfer_fix_amount' => 700,
                'prev_advance_balance' => 300,
                'recover' => 300,  // full recovery: surplus 300 withheld as cash deduction
            ]],
        ]);

        $item = PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(0.00, (float) $item->advance_balance);   // fully settled
        $this->assertEquals(0.00, (float) $item->advance_given);
        $this->assertEquals(300.00, (float) $item->advance_recovered);
    }

    public function test_no_advance_in_normal_week(): void
    {
        // Normal: earn 900, bank 700, cash 200 → no advance
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 150]);

        $this->post('/payroll/save-week', [
            'year' => 2025, 'week' => 10,
            'items' => [[
                'employee_id' => $emp->id, 'type' => 'daily_rate',
                'weekly_amount' => 900, 'cash' => 200, 'bank' => 700,
                'prev_advance_balance' => 0,
            ]],
        ]);

        $item = PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(0.00, (float) $item->advance_given);
        $this->assertEquals(0.00, (float) $item->advance_recovered);
        $this->assertEquals(0.00, (float) $item->advance_balance);
    }

    public function test_advance_balance_formula_js_equivalent(): void
    {
        // New formula: max(0, prev + given - recover)
        // given   = max(0, bankFix - earned)   — advance weeks only
        // recover = explicit admin input        — deducted from cash
        // Mirrors advanceBalance() in payroll.js
        $cases = [
            // prev, bankFix, earned, recover, expected
            ['prev' => 0,   'bankFix' => 700, 'earned' => 400, 'recover' => 0,   'expected' => 300], // advance given
            ['prev' => 300, 'bankFix' => 700, 'earned' => 900, 'recover' => 200, 'expected' => 100], // partial explicit recovery
            ['prev' => 300, 'bankFix' => 700, 'earned' => 1000, 'recover' => 300, 'expected' => 0],   // full recovery (surplus 300)
            ['prev' => 0,   'bankFix' => 700, 'earned' => 900, 'recover' => 0,   'expected' => 0],   // normal, no advance
            ['prev' => 100, 'bankFix' => 700, 'earned' => 700, 'recover' => 0,   'expected' => 100], // bank==earned, no change
            ['prev' => 300, 'bankFix' => 700, 'earned' => 900, 'recover' => 0,   'expected' => 300], // no explicit recover → balance stays
        ];

        foreach ($cases as $c) {
            $given = max(0, $c['bankFix'] - $c['earned']);
            $result = max(0, round($c['prev'] + $given - $c['recover'], 2));
            $this->assertEquals($c['expected'], $result, "prev={$c['prev']} bankFix={$c['bankFix']} earned={$c['earned']} recover={$c['recover']}");
        }
    }

    // ── Deactivated employees ─────────────────────────────────────────────────

    public function test_deactivated_employee_with_recorded_attendance_still_appears_flagged(): void
    {
        // Deactivating mid-week must not silently drop pay for days already worked —
        // the row stays (so it is still calculated) but is flagged as inactive.
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create([
            'name' => 'Deactivated Worker',
            'type' => 'daily_rate',
            'daily_rate' => 500,
            'is_active' => true,
        ]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ], 2026, 20, false);

        $emp->update(['is_active' => false]);

        $response = $this->get('/payroll?year=2026&week=20');

        $response->assertOk()
            ->assertSee('Deactivated Worker')  // still listed and still calculated
            ->assertSee('Inactive');           // but clearly flagged

        $this->assertEquals(1500.0, $response->viewData('rows')->first()['gross_amount']);
    }

    public function test_daily_pay_stops_from_the_deactivation_date(): void
    {
        // W33/2026 runs Mon 10 Aug – Sun 16 Aug. Deactivated on Tue 11 Aug means only
        // Mon 10 Aug is payable; the rest of the week is excluded.
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500, 'is_active' => true]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ], 2026, 33, false);

        $emp->update(['is_active' => false, 'deactivated_at' => '2026-08-11']);

        $rows = app(\App\Services\PayrollService::class)->buildRowsFromAttendance(2026, 33);
        $row = $rows->first();

        $this->assertEquals(1, $row['present_days'], 'only Mon 10 Aug is payable');
        $this->assertEquals(500.0, $row['gross_amount'], '1 day x 500');
    }

    public function test_hourly_pay_stops_from_the_deactivation_date(): void
    {
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'hourly', 'hourly_rate' => 10, 'hours_per_day' => 8, 'is_active' => true]);

        app(\App\Services\AttendanceService::class)->saveHourlyEmployee($emp->id, [
            'hours_map' => ['mon' => 8, 'tue' => 8, 'wed' => 8, 'thu' => 8, 'fri' => 8, 'sat' => 0, 'sun' => 0],
            'ot_map' => ['mon' => 2, 'tue' => 2, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ], 2026, 33, false);

        $emp->update(['is_active' => false, 'deactivated_at' => '2026-08-11']);

        $row = app(\App\Services\PayrollService::class)->buildRowsFromAttendance(2026, 33)->first();

        // Mon only: 8 total hours of which 2 are OT -> 6 regular + 2 OT.
        $this->assertEquals(6.0, $row['total_hours']);
        $this->assertEquals(2.0, $row['overtime_hours']);
        $this->assertEquals(80.0, $row['gross_amount'], '(6 + 2) x 10');
    }

    public function test_week_entirely_after_deactivation_pays_nothing(): void
    {
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500, 'is_active' => true]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
        ], 2026, 33, false);

        $emp->update(['is_active' => false, 'deactivated_at' => '2026-08-03']); // before the week starts

        $row = app(\App\Services\PayrollService::class)->buildRowsFromAttendance(2026, 33)->first();

        $this->assertEquals(0, $row['present_days']);
        $this->assertEquals(0.0, $row['gross_amount']);
    }

    public function test_week_entirely_before_deactivation_is_unaffected(): void
    {
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500, 'is_active' => true]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
        ], 2026, 33, false);

        $emp->update(['is_active' => false, 'deactivated_at' => '2026-09-01']); // long after

        $row = app(\App\Services\PayrollService::class)->buildRowsFromAttendance(2026, 33)->first();

        $this->assertEquals(5, $row['present_days']);
        $this->assertEquals(2500.0, $row['gross_amount']);
    }

    public function test_recalculate_from_attendance_keeps_the_deactivation_cutoff(): void
    {
        // Without the cutoff in AttendanceService, recalculating would silently
        // restore the full week's pay and undo the deactivation.
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500, 'is_active' => true]);

        $attendance = ['days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0]];
        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, $attendance, 2026, 33, false);

        $emp->update(['is_active' => false, 'deactivated_at' => '2026-08-11']);

        // Re-running attendance save is what "Recalculate from Attendance" does.
        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, $attendance, 2026, 33, false);

        $item = PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(1, $item->present_days, 'only Mon 10 Aug remains payable');
        $this->assertEquals(500.0, (float) $item->gross_amount);

        // The raw attendance record still shows every marked day.
        $this->assertEquals(6, \App\Models\DailyRateAttendance::where('employee_id', $emp->id)->first()->present_days);
    }

    public function test_status_endpoint_stores_the_chosen_cutoff_date_and_logs_history(): void
    {
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500, 'is_active' => true]);

        // Backdated cut-off: "he actually left last Friday".
        $this->put("/employees/{$emp->id}/status", [
            'is_active' => 0,
            'effective_from' => '2026-08-07',
        ])->assertSessionHasNoErrors();

        $this->assertFalse((bool) $emp->fresh()->is_active);
        $this->assertEquals('2026-08-07', $emp->fresh()->deactivated_at?->toDateString());
        $logged = $emp->statusChanges()->latest('id')->first();
        $this->assertFalse($logged->is_active);
        $this->assertEquals('2026-08-07', $logged->effective_from->toDateString());

        $this->put("/employees/{$emp->id}/status", [
            'is_active' => 1,
            'effective_from' => now()->toDateString(),
        ])->assertSessionHasNoErrors();

        $this->assertTrue((bool) $emp->fresh()->is_active);
        $this->assertNull($emp->fresh()->deactivated_at);
        $this->assertEquals(2, $emp->statusChanges()->count());
    }

    public function test_status_endpoint_rejects_a_future_effective_date(): void
    {
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500, 'is_active' => true]);

        $this->put("/employees/{$emp->id}/status", [
            'is_active' => 0,
            'effective_from' => now()->addWeek()->toDateString(),
        ])->assertSessionHasErrors('effective_from');

        $this->assertTrue((bool) $emp->fresh()->is_active);
    }

    public function test_manager_cannot_change_employment_status(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'is_active' => true]);

        $this->put("/employees/{$emp->id}/status", [
            'is_active' => 0,
            'effective_from' => now()->toDateString(),
        ])->assertForbidden();

        $this->assertTrue((bool) $emp->fresh()->is_active);
    }

    public function test_deactivated_employee_without_attendance_does_not_appear(): void
    {
        $this->actingAs($this->admin());
        Employee::factory()->create([
            'name' => 'Gone Worker',
            'type' => 'daily_rate',
            'daily_rate' => 500,
            'is_active' => false,
        ]);

        $this->get('/payroll?year=2026&week=21')
            ->assertOk()
            ->assertDontSee('Gone Worker');
    }

    // ── Export ────────────────────────────────────────────────────────────────

    public function test_export_shows_correct_cash_and_bank_when_payroll_never_explicitly_saved(): void
    {
        // Regression guard: AttendanceService always persists cash_amount=0 /
        // bank_amount=bank_transfer_fix_amount as a placeholder when attendance is
        // saved. The web page shows a client-computed preview (weekly - bank_fix)
        // for this "not yet saved" state, but the export used to read the raw 0
        // straight from the DB. The export must show the same figures as the screen.
        $admin = $this->admin();
        $this->actingAs($admin);
        $emp = Employee::factory()->create([
            'type' => 'daily_rate',
            'daily_rate' => 200,
            'bank_transfer_fix_amount' => 100,
        ]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ], 2026, 10, false);

        $this->assertDatabaseHas('payroll_items', [
            'employee_id' => $emp->id,
            'cash_amount' => 0,
            'bank_amount' => 100,
        ]);

        $response = $this->get('/payroll/export-week?year=2026&week=10');
        $response->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'payroll_export_test').'.xlsx';
        file_put_contents($tmp, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
        unlink($tmp);

        // 6 present days * 200 = 1200 gross/weekly; cash = 1200 - bank_fix(100) = 1100
        $this->assertEquals(1100, (float) $sheet->getCell('Q3')->getValue());
        $this->assertEquals(100, (float) $sheet->getCell('R3')->getValue());
    }

    public function test_weekly_export_uses_the_client_sheet_layout(): void
    {
        // The workbook mirrors the one the client has kept by hand for years:
        // attendance on the left, a red divider, pay on the right, with the employee
        // name repeated either side. Column positions are the contract here.
        $admin = $this->admin();
        $this->actingAs($admin);
        $emp = Employee::factory()->create([
            'type' => 'daily_rate',
            'daily_rate' => 100,
            'bank_transfer_fix_amount' => 450,
        ]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ], 2026, 31, false);

        $response = $this->get('/payroll/export-week?year=2026&week=31');
        $response->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'payroll_export_test').'.xlsx';
        file_put_contents($tmp, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
        unlink($tmp);

        $this->assertEquals('NO', $sheet->getCell('A1')->getValue());
        $this->assertEquals('WEEK 31', $sheet->getCell('B1')->getValue());
        $this->assertEquals('Employee Name', $sheet->getCell('B2')->getValue());
        $this->assertEquals('Mon', $sheet->getCell('C2')->getValue());
        $this->assertEquals('Sun', $sheet->getCell('I2')->getValue());
        $this->assertEquals('OT', $sheet->getCell('J1')->getValue());
        $this->assertEquals('Total WD', $sheet->getCell('K1')->getValue());
        $this->assertEquals('Total WH', $sheet->getCell('L1')->getValue());
        $this->assertEquals('WEEK 31', $sheet->getCell('N1')->getValue());
        $this->assertEquals('Employee Name', $sheet->getCell('N2')->getValue());
        $this->assertEquals('Rate', $sheet->getCell('O1')->getValue());
        $this->assertEquals('Total Weekly', $sheet->getCell('P1')->getValue());
        $this->assertEquals('CASH', $sheet->getCell('Q1')->getValue());
        $this->assertEquals('Bank Weekly', $sheet->getCell('R1')->getValue());
        $this->assertEquals('Bank Monthly', $sheet->getCell('S1')->getValue());
        // No holiday this month, so the BH pair is absent and Comments/Check
        // sit where they always did.
        $this->assertEquals('Comments', $sheet->getCell('T1')->getValue());
        $this->assertEquals('Check', $sheet->getCell('U1')->getValue());

        // First data row: numbered, name on both sides of the divider, Sunday closed.
        $this->assertEquals(1, (int) $sheet->getCell('A3')->getValue());
        $this->assertEquals($emp->name, $sheet->getCell('B3')->getValue());
        $this->assertEquals($emp->name, $sheet->getCell('N3')->getValue());
        $this->assertEquals('IN', $sheet->getCell('C3')->getValue());
        $this->assertEquals('CLOSED', $sheet->getCell('I3')->getValue());
        $this->assertEquals(6, (float) $sheet->getCell('K3')->getValue());
        $this->assertEquals('no', $sheet->getCell('U3')->getValue());

        // Bank Monthly annualises the standing order: 450 * 52 / 12.
        $this->assertEqualsWithDelta(1950.0, (float) $sheet->getCell('S3')->getValue(), 0.01);

        // The red divider column carries no data.
        $this->assertEmpty($sheet->getCell('M3')->getValue());
    }

    public function test_hourly_day_cells_show_hours_with_the_split(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $emp = Employee::factory()->create([
            'type' => 'hourly', 'hourly_rate' => 17.50, 'hours_per_day' => 8,
        ]);

        app(\App\Services\AttendanceService::class)->saveHourlyEmployee($emp->id, [
            'hours_map' => ['mon' => 10, 'tue' => 11, 'wed' => 8],
        ], 2026, 31, false);

        $response = $this->get('/payroll/export-week?year=2026&week=31');
        $response->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'payroll_export_test').'.xlsx';
        file_put_contents($tmp, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
        unlink($tmp);

        $this->assertEquals('10hrs (8+2)', $sheet->getCell('C3')->getValue());
        $this->assertEquals('11hrs (8+3)', $sheet->getCell('D3')->getValue());
        $this->assertEquals('8hrs', $sheet->getCell('E3')->getValue(), 'a day at the norm carries no bracket');
        $this->assertEquals(5, (float) $sheet->getCell('J3')->getValue(), 'OT column holds the hours');
        $this->assertEquals(3, (float) $sheet->getCell('K3')->getValue(), 'Total WD counts days worked');
        $this->assertEquals(29, (float) $sheet->getCell('L3')->getValue(), 'Total WH is all hours worked');
        $this->assertEquals('-', $sheet->getCell('S3')->getValue(), 'hourly staff have no monthly standing order');
    }

    public function test_export_arrears_moved_out_of_the_workbook(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 100]);

        // Attendance is always saved before payroll in real usage — save it first so
        // this exercises the common merge path, not the rare "attendance missing" one.
        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ], 2026, 12, false);

        $this->post('/payroll/save-week', [
            'year' => 2026, 'week' => 12,
            'items' => [[
                'employee_id' => $emp->id, 'type' => 'daily_rate',
                'weekly_amount' => 400, 'cash' => 0, 'bank' => 700,
                'prev_advance_balance' => 0,
            ]],
        ]);

        $response = $this->get('/payroll/export-week?year=2026&week=12');
        $response->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'payroll_export_test').'.xlsx';
        file_put_contents($tmp, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
        unlink($tmp);

        // The client sheet has no arrears column, so the workbook no longer carries
        // one. The figure is still reported on the payroll page and the PDF export.
        foreach (range('A', 'W') as $letter) {
            $this->assertNotEquals('Arrears', $sheet->getCell($letter.'1')->getValue());
        }

        $this->get('/payroll/export-week-pdf?year=2026&week=12')->assertOk();
    }

    public function test_guest_cannot_export_week_pdf(): void
    {
        $this->get('/payroll/export-week-pdf?year=2026&week=10')
            ->assertRedirect(route('login'));
    }

    public function test_manager_cannot_export_week_pdf(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'manager']));

        $this->get('/payroll/export-week-pdf?year=2026&week=10')
            ->assertForbidden();
    }

    public function test_export_week_pdf_returns_404_when_no_run_exists(): void
    {
        $this->actingAs($this->admin());

        $this->get('/payroll/export-week-pdf?year=2026&week=99')
            ->assertNotFound();
    }

    public function test_admin_can_export_week_pdf(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 200]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ], 2026, 15, false);

        $response = $this->get('/payroll/export-week-pdf?year=2026&week=15');

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    // ── Monthly settlement clears weekly carry-forward ────────────────────────

    public function test_monthly_settlement_clears_weekly_carry_forward(): void
    {
        // W22 2025 (May): advance given, balance = 1000
        // Monthly May saved with advance_settled = 1000 (full clear)
        // W23 2025 (June): carry-forward should be 0 (monthly settlement cleared it)
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create([
            'type' => 'daily_rate',
            'daily_rate' => 200,
            'bank_transfer_fix_amount' => 2000,
        ]);

        // Save W22 attendance → advance 1000 stored in PayrollItem
        $attService = app(\App\Services\AttendanceService::class);
        $attService->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
        ], 2025, 22, false);

        // Create a monthly PayrollRun for May 2025 with advance_recovered = 1000 (full settlement)
        $monthlyRun = \App\Models\PayrollRun::create([
            'year' => 2025,
            'week_number' => 0,
            'period_type' => 'monthly',
            'month' => '2025-05',
            'status' => 'draft',
            'created_by' => auth()->id(),
            'generated_at' => now(),
        ]);
        \App\Models\PayrollItem::create([
            'payroll_run_id' => $monthlyRun->id,
            'employee_id' => $emp->id,
            'type' => 'daily_rate',
            'gross_amount' => 1000,
            'cash_amount' => 0,
            'bank_amount' => 2000,
            'weekly_amount' => 1000,
            'advance_recovered' => 1000,  // monthly settlement: fully cleared
            'advance_balance' => 0,
        ]);

        // W23 June: save attendance — carry-forward should be 0 (monthly settled)
        $attService->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
            'overtime_map' => ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 1200, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ], 2025, 23, false);

        $item23 = \App\Models\PayrollItem::where('employee_id', $emp->id)
            ->orderBy('id', 'desc')->first();

        $this->assertEquals(0.0, (float) $item23->advance_balance,
            'Monthly settlement for May cleared the carry-forward into June');
        $this->assertEquals(0.0, (float) $item23->advance_given,
            'No new advance in W23 (earned 2200 > bank_fix 2000)');
    }

    public function test_partial_monthly_settlement_reduces_carry_forward(): void
    {
        // W22 May: advance 1000. Monthly May settled 600 (partial).
        // W23 June: carry-forward should be 400, not 1000.
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create([
            'type' => 'daily_rate',
            'daily_rate' => 200,
            'bank_transfer_fix_amount' => 2000,
        ]);

        $attService = app(\App\Services\AttendanceService::class);
        $attService->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
        ], 2025, 22, false);

        $monthlyRun = \App\Models\PayrollRun::create([
            'year' => 2025,
            'week_number' => 0,
            'period_type' => 'monthly',
            'month' => '2025-05',
            'status' => 'draft',
            'created_by' => auth()->id(),
            'generated_at' => now(),
        ]);
        \App\Models\PayrollItem::create([
            'payroll_run_id' => $monthlyRun->id,
            'employee_id' => $emp->id,
            'type' => 'daily_rate',
            'gross_amount' => 1000,
            'cash_amount' => 0,
            'bank_amount' => 2000,
            'weekly_amount' => 1000,
            'advance_recovered' => 600,   // partial monthly settlement
            'advance_balance' => 400,
        ]);

        $attService->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
            'overtime_map' => ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 1200, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ], 2025, 23, false);

        $item23 = \App\Models\PayrollItem::where('employee_id', $emp->id)
            ->orderBy('id', 'desc')->first();

        $this->assertEquals(400.0, (float) $item23->advance_balance,
            'Partial monthly settlement: 400 carries forward into June');
    }

    public function test_bank_holiday_columns_appear_in_the_weekly_export(): void
    {
        \App\Models\Holiday::factory()->on('2026-07-27')->create(['name' => 'August BH']);

        $admin = $this->admin();
        $this->actingAs($admin);
        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135,
            'bh_bank_percent' => 40, 'bank_transfer_fix_amount' => 0,
        ]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ], 2026, 31, false);

        $response = $this->get('/payroll/export-week?year=2026&week=31');
        $response->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'payroll_export_test').'.xlsx';
        file_put_contents($tmp, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
        unlink($tmp);

        // 6 days x 135 = 810 is what the week's work earned, and the weekly total
        // stays there — the 81 cash share of the 135 premium is shown in its own
        // column and reaches the employee through cash (810 - 0 bank + 81 = 891).
        // The 54 bank share is a separate transfer again.
        $this->assertEqualsWithDelta(810.0, (float) $sheet->getCell('P3')->getValue(), 0.01, 'weekly total, settlement excluded');
        $this->assertEqualsWithDelta(891.0, (float) $sheet->getCell('Q3')->getValue(), 0.01, 'cash carries the 81 on top');
        $this->assertEqualsWithDelta(81.0, (float) $sheet->getCell('T3')->getValue(), 0.01, 'BH cash');
        $this->assertEqualsWithDelta(54.0, (float) $sheet->getCell('U3')->getValue(), 0.01, 'BH bank');

        // The holiday is named on the day column and flagged on the cell.
        $this->assertStringContainsString('BH', (string) $sheet->getCell('C2')->getValue());
        $this->assertSame('IN BH', $sheet->getCell('C3')->getValue());
        $this->assertSame('IN', $sheet->getCell('D3')->getValue(), 'an ordinary day is untouched');
    }

    public function test_a_bank_holiday_week_reports_no_arrears_on_the_payroll_page(): void
    {
        // Guards the isSaved heuristic: the premium moves bank_amount off
        // bank_transfer_fix_amount, which used to read as "already saved".
        \App\Models\Holiday::factory()->on('2026-07-27')->create();

        $this->actingAs($this->admin());
        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135,
            'bh_bank_percent' => 40, 'bank_transfer_fix_amount' => 200,
        ]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1],
        ], 2026, 31, false);

        $rows = $this->get('/payroll?year=2026&week=31')->assertOk()->viewData('rows');
        $row = $rows->firstWhere('employee.id', $emp->id);

        // 3 days x 135 = 405 is the weekly total; the 81 cash share settles on top
        // of it, so gross is 486 and that is what the cash/bank split works from.
        $this->assertEqualsWithDelta(405.0, (float) $row['weekly_amount'], 0.01, 'the days worked, settlement excluded');
        $this->assertEqualsWithDelta(486.0, (float) $row['gross_amount'], 0.01);
        $this->assertEqualsWithDelta(135.0, (float) $row['bh_amount'], 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $row['arrears'], 0.01, 'no phantom advance');
        $this->assertEqualsWithDelta(200.0, (float) $row['bank_amount'], 0.01, 'the fixed amount, untouched');
        $this->assertEqualsWithDelta(54.0, (float) $row['bh_bank'], 0.01, 'separate transfer on top');
        $this->assertEqualsWithDelta(286.0, (float) $row['cash_amount'], 0.01, '486 - 200, incl. 81 BH cash');
    }

    public function test_the_bank_holiday_cash_share_tops_up_cash_without_moving_the_weekly_total_or_bank(): void
    {
        // The row the office reads: six days at 135 is 810 and stays 810, the bank
        // transfer stays at its fixed 500, and the 49 cash share of the premium is
        // handed over as extra cash — 310 normal + 49 = 359. The 86 bank share is
        // its own transfer on top.
        \App\Models\Holiday::factory()->on('2026-07-27')->create(['name' => 'August BH']);

        $this->actingAs($this->admin());
        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135,
            'bh_bank_percent' => 63.7, 'bank_transfer_fix_amount' => 500,
        ]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ], 2026, 31, false);

        $row = $this->get('/payroll?year=2026&week=31')->assertOk()
            ->viewData('rows')->firstWhere('employee.id', $emp->id);

        $this->assertEqualsWithDelta(810.0, (float) $row['weekly_amount'], 0.01, 'weekly total is the work alone');
        $this->assertEqualsWithDelta(359.0, (float) $row['cash_amount'], 0.01, '310 + the 49 cash share');
        $this->assertEqualsWithDelta(500.0, (float) $row['bank_amount'], 0.01, 'the fixed transfer, unmoved by the premium');
        $this->assertEqualsWithDelta(49.0, (float) $row['bh_cash'], 0.01);
        $this->assertEqualsWithDelta(86.0, (float) $row['bh_bank'], 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $row['arrears'], 0.01, 'no phantom advance');

        // Cash and bank still account for every euro of gross, and the premium
        // is genuinely double pay once its bank share is counted: 859 + 86 = 945.
        $this->assertEqualsWithDelta(859.0, (float) $row['gross_amount'], 0.01);
        $this->assertEqualsWithDelta(
            (float) $row['gross_amount'],
            (float) $row['cash_amount'] + (float) $row['bank_amount'],
            0.01,
            'cash + bank reconcile to gross'
        );
        $this->assertEqualsWithDelta(945.0, (float) $row['gross_amount'] + (float) $row['bh_bank'], 0.01);
    }

    public function test_saving_a_settlement_week_keeps_the_weekly_total_and_gross_apart(): void
    {
        // Saving the page posts the weekly total back, so gross has to be rebuilt
        // from it plus the cash share rather than stored as the same figure.
        \App\Models\Holiday::factory()->on('2026-07-27')->create();

        $this->actingAs($this->admin());
        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135,
            'bh_bank_percent' => 63.7, 'bank_transfer_fix_amount' => 500,
        ]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ], 2026, 31, false);

        $this->post('/payroll/save-week', [
            'year' => 2026,
            'week' => 31,
            'items' => [[
                'employee_id' => $emp->id,
                'type' => 'daily_rate',
                'weekly_amount' => 810,
                'cash' => 359,
                'bank' => 500,
                'bh_amount' => 135,
                'bh_cash' => 49,
                'bh_bank' => 86,
            ]],
        ])->assertRedirect();

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->firstOrFail();

        $this->assertEqualsWithDelta(810.0, (float) $item->weekly_amount, 0.01);
        $this->assertEqualsWithDelta(859.0, (float) $item->gross_amount, 0.01, 'gross rebuilt as 810 + 49');
        $this->assertEqualsWithDelta(359.0, (float) $item->cash_amount, 0.01, 'the cash is not clamped back to 310');
        $this->assertEqualsWithDelta(500.0, (float) $item->bank_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $item->advance_given, 0.01, 'bank never exceeded gross');
        $this->assertEqualsWithDelta(0.0, (float) $item->advance_balance, 0.01);
    }

    /**
     * Saves one settlement-week row and hands back what was stored.
     * gross is 859: six days at 135 plus the 49 cash share of a 135 premium.
     */
    private function saveSettlementWeekCash(
        Employee $emp,
        float $cash,
        float $bank,
        float $recover = 0,
        float $prevBalance = 0,
        float|string|null $bhCashOverride = null
    ): PayrollItem {
        $this->post('/payroll/save-week', [
            'year' => 2026,
            'week' => 31,
            'items' => [[
                'employee_id' => $emp->id,
                'type' => 'daily_rate',
                'weekly_amount' => 810,
                'cash' => $cash,
                'bank' => $bank,
                'bh_amount' => 135,
                'bh_cash' => 49,
                'bh_bank' => 86,
                'bh_cash_override' => $bhCashOverride,
                'recover' => $recover,
                'prev_advance_balance' => $prevBalance,
            ]],
        ])->assertRedirect();

        return PayrollItem::where('employee_id', $emp->id)->firstOrFail();
    }

    private function settlementWeekEmployee(float $bankFix = 500): Employee
    {
        \App\Models\Holiday::factory()->on('2026-07-27')->create();

        $this->actingAs($this->admin());
        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135,
            'bh_bank_percent' => 63.7, 'bank_transfer_fix_amount' => $bankFix,
        ]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ], 2026, 31, false);

        return $emp;
    }

    public function test_the_page_spells_out_which_seven_days_the_week_covers(): void
    {
        // Week 31 of 2026 runs Mon 27 Jul .. Sun 2 Aug. Getting this wrong would
        // be worse than not showing it, so the dates are asserted, not just the
        // presence of the cards.
        $this->actingAs($this->admin());

        $html = $this->get('/payroll?year=2026&week=31')->assertOk()->getContent();

        $this->assertStringContainsString('27 Jul', $html);
        $this->assertStringContainsString('Mon', $html);
        $this->assertStringContainsString('2 Aug', $html);
        $this->assertStringContainsString('Sun', $html);
        // Read by anyone who cannot see the two cards as a range.
        $this->assertStringContainsString('Payroll week 31: 27 Jul 2026 to 2 Aug 2026', $html);
    }

    public function test_the_week_range_shows_before_any_payroll_has_been_generated(): void
    {
        // The range is orientation, not a result — it has to be there on a week
        // nobody has touched yet, which is exactly when it is most needed.
        $this->actingAs($this->admin());

        $this->get('/payroll?year=2026&week=44')
            ->assertOk()
            ->assertSee('26 Oct')
            ->assertSee('1 Nov');
    }

    public function test_the_current_week_is_flagged_on_the_week_selector(): void
    {
        // The note hangs off the selector it describes, so the selector squares
        // off where the two meet.
        $this->actingAs($this->admin());

        $now = now();
        $html = $this->get("/payroll?year={$now->isoWeekYear}&week={$now->isoWeek}")->assertOk()->getContent();

        $this->assertStringContainsString('Current week', $html);
        $this->assertStringContainsString('rounded-b-lg border border-t-0 border-emerald-200', $html);
        $this->assertStringContainsString('rounded-t-lg', $html, 'the selector joins the note below it');

        $past = now()->subWeeks(6);
        $this->get("/payroll?year={$past->isoWeekYear}&week={$past->isoWeek}")
            ->assertOk()
            ->assertDontSee('Current week');
    }

    public function test_the_exports_are_offered_as_labelled_icons(): void
    {
        // Icon-only controls still have to say what they do for anyone reading
        // the page with a screen reader or hovering for a tooltip.
        $this->actingAs($this->admin());
        $emp = $this->settlementWeekEmployee();

        $html = $this->get('/payroll?year=2026&week=31')->assertOk()->getContent();

        $this->assertStringContainsString('images/xls.svg', $html);
        $this->assertStringContainsString('images/pdf.svg', $html);
        $this->assertStringContainsString('data-tooltip="Export report in Excel"', $html);
        $this->assertStringContainsString('data-tooltip="Export report in PDF"', $html);
        $this->assertStringContainsString('aria-label="Export report in Excel"', $html);
        $this->assertStringContainsString('aria-label="Export report in PDF"', $html);
    }

    public function test_the_exports_sit_with_the_weeks_other_actions(): void
    {
        // They belong next to recalculating and finalising, not up in the toolbar
        // that only chooses which week you are looking at.
        $this->actingAs($this->admin());
        $this->settlementWeekEmployee();

        $html = $this->get('/payroll?year=2026&week=31')->assertOk()->getContent();

        $banner = substr($html, strpos($html, 'Recalculate from Attendance'));
        $banner = substr($banner, 0, strpos($banner, 'Finalize Week'));

        $this->assertStringContainsString('images/xls.svg', $banner, 'Excel sits between the two buttons');
        $this->assertStringContainsString('images/pdf.svg', $banner, 'PDF sits between the two buttons');
    }

    public function test_the_exports_are_available_on_a_finalised_week_too(): void
    {
        // A finalised week renders a different banner and is the one most likely
        // to be exported, so the icons have to be in both states.
        $this->actingAs($this->admin());
        $this->settlementWeekEmployee();

        PayrollRun::where('year', 2026)->where('week_number', 31)->update(['status' => 'final']);

        $html = $this->get('/payroll?year=2026&week=31')->assertOk()->getContent();

        $this->assertStringContainsString('Revert to Draft', $html, 'the finalised banner is the one rendering');
        $this->assertStringContainsString('images/xls.svg', $html);
        $this->assertStringContainsString('images/pdf.svg', $html);
    }

    public function test_the_cash_cell_carries_a_tab_showing_the_bank_holiday_share_inside_it(): void
    {
        $emp = $this->settlementWeekEmployee();

        $html = $this->get('/payroll?year=2026&week=31')->assertOk()->getContent();

        // The figure is rendered client-side now that the split is editable, so
        // the markup is asserted on the binding it will render from.
        $this->assertStringContainsString('rounded-b-lg border border-t-0 border-violet-200', $html, 'the tab joins the cell above it');
        $this->assertStringContainsString('.bh_cash > 0', $html, 'the tab follows the BH cash figure');
        $this->assertStringContainsString("rounded-t-lg' : 'rounded-lg'", $html, 'the cash input squares off where the tab meets it');
    }

    public function test_an_ordinary_week_shows_no_bank_holiday_tab(): void
    {
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135, 'bank_transfer_fix_amount' => 500,
        ]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1],
        ], 2026, 30, false);

        $html = $this->get('/payroll?year=2026&week=30')->assertOk()->getContent();

        $this->assertStringNotContainsString('border-t-0 border-violet-200', $html, 'no tab without a premium');
        $this->assertStringNotContainsString('updateBhCash', $html, 'and nothing to edit either');
    }

    public function test_the_bank_holiday_split_can_be_set_by_hand_and_the_bank_side_follows(): void
    {
        // The premium is earned, not entered: whatever cash is set to, bank is the
        // rest of the 135 and the two still add up.
        $emp = $this->settlementWeekEmployee();

        // Raising the premium's cash side raises what is earned, so the page
        // re-settles cash against the unchanged transfer before posting: the
        // 870 now payable less the 500 bank leaves 370 in hand.
        $item = $this->saveSettlementWeekCash($emp, cash: 370, bank: 500, bhCashOverride: 60);

        $this->assertEqualsWithDelta(135.0, (float) $item->bh_amount, 0.01, 'the premium itself is untouched');
        $this->assertEqualsWithDelta(60.0, (float) $item->bh_cash, 0.01);
        $this->assertEqualsWithDelta(75.0, (float) $item->bh_bank, 0.01, 'the bank side absorbed the change');
        $this->assertEqualsWithDelta(60.0, (float) $item->bh_cash_override, 0.01, 'remembered as hand-set');
        $this->assertEqualsWithDelta(
            (float) $item->bh_amount,
            (float) $item->bh_cash + (float) $item->bh_bank,
            0.01,
            'the split still accounts for the whole premium'
        );

        // Gross follows the new cash side: 810 + 60.
        $this->assertEqualsWithDelta(870.0, (float) $item->gross_amount, 0.01);
        $this->assertEqualsWithDelta(370.0, (float) $item->cash_amount, 0.01, '310 weekly surplus + the 60');
        $this->assertEqualsWithDelta(500.0, (float) $item->bank_amount, 0.01, 'the weekly transfer is unmoved');
    }

    public function test_an_overridden_split_records_the_percentage_actually_paid(): void
    {
        // applied_bh_bank_percent is what the payslip reports, so it has to describe
        // the split the employee got rather than the one the setting would give.
        $emp = $this->settlementWeekEmployee();

        $item = $this->saveSettlementWeekCash($emp, cash: 359, bank: 500, bhCashOverride: 60);

        // 75 of 135 went to bank.
        $this->assertEqualsWithDelta(55.56, (float) $item->applied_bh_bank_percent, 0.01);
    }

    public function test_an_override_larger_than_the_premium_is_clamped_to_it(): void
    {
        // Attendance can change after a split is set, so a stale override must
        // never pay out more cash than the premium actually earned.
        $emp = $this->settlementWeekEmployee();

        $item = $this->saveSettlementWeekCash($emp, cash: 359, bank: 500, bhCashOverride: 500);

        $this->assertEqualsWithDelta(135.0, (float) $item->bh_cash, 0.01, 'capped at the whole premium');
        $this->assertEqualsWithDelta(0.0, (float) $item->bh_bank, 0.01);
    }

    public function test_a_hand_set_split_survives_a_week_refresh(): void
    {
        // Refreshing re-derives the premium from attendance. It must not quietly
        // undo a deliberate decision about how that premium was paid.
        $emp = $this->settlementWeekEmployee();
        $this->saveSettlementWeekCash($emp, cash: 359, bank: 500, bhCashOverride: 60);

        $this->post('/payroll/refresh-week', ['year' => 2026, 'week' => 31])->assertRedirect();

        $item = PayrollItem::where('employee_id', $emp->id)->firstOrFail();

        $this->assertEqualsWithDelta(60.0, (float) $item->bh_cash, 0.01, 'the hand-set split held');
        $this->assertEqualsWithDelta(75.0, (float) $item->bh_bank, 0.01);
        $this->assertEqualsWithDelta(60.0, (float) $item->bh_cash_override, 0.01);
        $this->assertEqualsWithDelta(870.0, (float) $item->gross_amount, 0.01, 'and gross was rebuilt around it');
    }

    public function test_clearing_the_override_hands_the_split_back_to_the_percentage(): void
    {
        $emp = $this->settlementWeekEmployee();
        $this->saveSettlementWeekCash($emp, cash: 359, bank: 500, bhCashOverride: 60);

        // Reset posts an empty override, the same as the reset button does.
        $item = $this->saveSettlementWeekCash($emp, cash: 359, bank: 500, bhCashOverride: '');

        $this->assertNull($item->bh_cash_override);
        $this->assertEqualsWithDelta(49.0, (float) $item->bh_cash, 0.01, 'back to 63.7% bank');
        $this->assertEqualsWithDelta(86.0, (float) $item->bh_bank, 0.01);
        $this->assertEqualsWithDelta(63.7, (float) $item->applied_bh_bank_percent, 0.01);
    }

    public function test_a_week_without_an_override_still_follows_the_employee_percentage(): void
    {
        $emp = $this->settlementWeekEmployee();

        $item = $this->saveSettlementWeekCash($emp, cash: 359, bank: 500);

        $this->assertNull($item->bh_cash_override);
        $this->assertEqualsWithDelta(49.0, (float) $item->bh_cash, 0.01);
        $this->assertEqualsWithDelta(86.0, (float) $item->bh_bank, 0.01);
    }

    public function test_the_payroll_page_offers_the_split_as_editable_fields(): void
    {
        $emp = $this->settlementWeekEmployee();

        $html = $this->get('/payroll?year=2026&week=31')->assertOk()->getContent();

        $this->assertStringContainsString('updateBhCash', $html);
        $this->assertStringContainsString('updateBhBank', $html);
        $this->assertStringContainsString('resetBhSplit', $html, 'and a way back to the percentage');
    }

    public function test_lowering_cash_moves_the_difference_into_bank(): void
    {
        // Cash leads and bank absorbs, so the row always accounts for all 859.
        $emp = $this->settlementWeekEmployee();

        $item = $this->saveSettlementWeekCash($emp, cash: 300, bank: 500);

        $this->assertEqualsWithDelta(300.0, (float) $item->cash_amount, 0.01);
        $this->assertEqualsWithDelta(559.0, (float) $item->bank_amount, 0.01, 'bank took the 59 that cash gave up');
        $this->assertEqualsWithDelta(859.0, (float) $item->cash_amount + (float) $item->bank_amount, 0.01);
        $this->assertEqualsWithDelta(0.0, (float) $item->advance_given, 0.01, 'moving money between channels is not an advance');
    }

    public function test_raising_cash_takes_the_difference_out_of_bank(): void
    {
        $emp = $this->settlementWeekEmployee();

        $item = $this->saveSettlementWeekCash($emp, cash: 600, bank: 500);

        $this->assertEqualsWithDelta(600.0, (float) $item->cash_amount, 0.01);
        $this->assertEqualsWithDelta(259.0, (float) $item->bank_amount, 0.01);
        $this->assertEqualsWithDelta(859.0, (float) $item->cash_amount + (float) $item->bank_amount, 0.01);
    }

    public function test_cash_cannot_be_set_below_the_bank_holiday_cash_share(): void
    {
        // The 49 is the cash half of a split bh_bank_percent already decided, so
        // it is not the admin's to re-route through the bank.
        $emp = $this->settlementWeekEmployee();

        $item = $this->saveSettlementWeekCash($emp, cash: 0, bank: 500);

        $this->assertEqualsWithDelta(49.0, (float) $item->cash_amount, 0.01, 'floored at the BH cash share');
        $this->assertEqualsWithDelta(810.0, (float) $item->bank_amount, 0.01);
        $this->assertEqualsWithDelta(859.0, (float) $item->cash_amount + (float) $item->bank_amount, 0.01);
    }

    public function test_an_ordinary_week_has_no_cash_floor(): void
    {
        // Without a premium there is no earmarked cash, so all of it may go by bank.
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135, 'bank_transfer_fix_amount' => 500,
        ]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ], 2026, 30, false);

        $this->post('/payroll/save-week', [
            'year' => 2026, 'week' => 30,
            'items' => [[
                'employee_id' => $emp->id, 'type' => 'daily_rate',
                'weekly_amount' => 810, 'cash' => 0, 'bank' => 500,
                'bh_cash' => 0, 'bh_bank' => 0,
            ]],
        ])->assertRedirect();

        $item = PayrollItem::where('employee_id', $emp->id)->firstOrFail();

        $this->assertEqualsWithDelta(0.0, (float) $item->cash_amount, 0.01);
        $this->assertEqualsWithDelta(810.0, (float) $item->bank_amount, 0.01);
    }

    public function test_recovering_an_advance_withholds_from_cash_without_inflating_bank(): void
    {
        // A recovery is money not paid at all, so unlike a cash edit it must not
        // reappear in the bank transfer.
        $emp = $this->settlementWeekEmployee();

        $item = $this->saveSettlementWeekCash($emp, cash: 259, bank: 500, recover: 100, prevBalance: 100);

        $this->assertEqualsWithDelta(100.0, (float) $item->advance_recovered, 0.01);
        $this->assertEqualsWithDelta(259.0, (float) $item->cash_amount, 0.01, '859 - 100 recovered - 500 bank');
        $this->assertEqualsWithDelta(500.0, (float) $item->bank_amount, 0.01, 'the transfer is untouched by a recovery');
        $this->assertEqualsWithDelta(0.0, (float) $item->advance_balance, 0.01, 'the 100 is cleared');
        $this->assertEqualsWithDelta(
            759.0,
            (float) $item->cash_amount + (float) $item->bank_amount,
            0.01,
            'paid out = gross less the recovery'
        );
    }

    public function test_a_recovery_beyond_the_outstanding_advance_is_capped(): void
    {
        $emp = $this->settlementWeekEmployee();

        $item = $this->saveSettlementWeekCash($emp, cash: 100, bank: 500, recover: 400, prevBalance: 100);

        $this->assertEqualsWithDelta(100.0, (float) $item->advance_recovered, 0.01, 'only what was owed');
        $this->assertEqualsWithDelta(759.0, (float) $item->cash_amount + (float) $item->bank_amount, 0.01);
    }

    public function test_giving_an_advance_still_transfers_more_than_was_earned(): void
    {
        // Bank above what is payable is how an advance is handed out, and must
        // survive the cash-leads rule rather than being clamped back down.
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135, 'bank_transfer_fix_amount' => 500,
        ]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1],
        ], 2026, 30, false);

        $this->post('/payroll/save-week', [
            'year' => 2026, 'week' => 30,
            'items' => [[
                'employee_id' => $emp->id, 'type' => 'daily_rate',
                'weekly_amount' => 270, 'cash' => 0, 'bank' => 500,
                'bh_cash' => 0, 'bh_bank' => 0,
            ]],
        ])->assertRedirect();

        $item = PayrollItem::where('employee_id', $emp->id)->firstOrFail();

        $this->assertEqualsWithDelta(500.0, (float) $item->bank_amount, 0.01, 'the transfer stands');
        $this->assertEqualsWithDelta(0.0, (float) $item->cash_amount, 0.01);
        $this->assertEqualsWithDelta(230.0, (float) $item->advance_given, 0.01, '500 - 270 earned');
        $this->assertEqualsWithDelta(230.0, (float) $item->advance_balance, 0.01);
    }

    public function test_an_ordinary_week_still_splits_straight_off_the_weekly_total(): void
    {
        // Without a settlement, weekly total and gross are the same number, so
        // nothing about the ordinary case moved.
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create([
            'type' => 'daily_rate', 'daily_rate' => 135,
            'bh_bank_percent' => 63.7, 'bank_transfer_fix_amount' => 500,
        ]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ], 2026, 30, false);

        $row = $this->get('/payroll?year=2026&week=30')->assertOk()
            ->viewData('rows')->firstWhere('employee.id', $emp->id);

        $this->assertEqualsWithDelta(810.0, (float) $row['weekly_amount'], 0.01);
        $this->assertEqualsWithDelta(810.0, (float) $row['gross_amount'], 0.01);
        $this->assertEqualsWithDelta(310.0, (float) $row['cash_amount'], 0.01, '810 - 500, no premium in play');
        $this->assertEqualsWithDelta(500.0, (float) $row['bank_amount'], 0.01);
    }

    public function test_bank_holiday_columns_are_absent_outside_the_settlement_week(): void
    {
        // Week 30 of 2026 is an ordinary week; July settles in week 31.
        \App\Models\Holiday::factory()->on('2026-07-22')->create();

        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 135]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['wed' => 1],
        ], 2026, 30, false);

        $this->get('/payroll?year=2026&week=30')->assertOk()->assertViewHas('showBankHoliday', false);

        $response = $this->get('/payroll/export-week?year=2026&week=30');
        $tmp = tempnam(sys_get_temp_dir(), 'payroll_export_test').'.xlsx';
        file_put_contents($tmp, $response->streamedContent());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmp)->getActiveSheet();
        unlink($tmp);

        $this->assertEquals('Comments', $sheet->getCell('T1')->getValue(), 'no BH pair on an ordinary week');
    }

    public function test_settlement_week_without_any_holiday_hides_the_columns(): void
    {
        // Week 31 settles July, but July has no holiday at all.
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 135]);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee($emp->id, [
            'days' => ['mon' => 1],
        ], 2026, 31, false);

        $this->get('/payroll?year=2026&week=31')->assertOk()->assertViewHas('showBankHoliday', false);
    }
}
