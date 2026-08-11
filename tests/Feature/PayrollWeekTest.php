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
        $this->assertEquals(1100, (float) $sheet->getCell('O5')->getValue());
        $this->assertEquals(100, (float) $sheet->getCell('P5')->getValue());
    }

    public function test_export_includes_arrears_column(): void
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

        $this->assertEquals('Arrears', $sheet->getCell('Q3')->getValue());
        $this->assertEquals(300, (float) $sheet->getCell('Q5')->getValue());
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
}
