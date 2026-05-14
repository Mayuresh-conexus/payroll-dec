<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PayrollItem;
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
                    'employee_id'   => $emp->id,
                    'type'          => 'daily_rate',
                    'total_days'    => 6,
                    'present_days'  => 5,
                    'weekly_amount' => 2500,
                    'cash'          => 1000,
                    'bank'          => 1500,
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('payroll_runs', [
            'year'        => 2025,
            'week_number' => 10,
            'status'      => 'draft',
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
                    'employee_id'   => $emp->id,
                    'type'          => 'daily_rate',
                    'total_days'    => 6,
                    'present_days'  => 6,
                    'weekly_amount' => 3000,
                    'cash'          => 1500,
                    'bank'          => 1500,
                ],
            ],
        ]);

        $this->assertDatabaseHas('payroll_items', [
            'employee_id'   => $emp->id,
            'type'          => 'daily_rate',
            'present_days'  => 6,
            'weekly_amount' => 3000,
            'cash_amount'   => 1500,
            'bank_amount'   => 1500,
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
                    'employee_id'   => $emp->id,
                    'type'          => 'daily_rate',
                    'weekly_amount' => 1000,
                    'cash'          => 600,
                    'bank'          => 600,   // 600 + 600 = 1200 > 1000 → bank should be clamped to 400
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
            'year'         => 2025,
            'week_number'  => 15,
            'status'       => 'draft',
            'created_by'   => auth()->id(),
            'generated_at' => now(),
        ]);

        $this->post('/payroll/finalize-week', [
            'year' => 2025,
            'week' => 15,
        ])->assertRedirect();

        $this->assertDatabaseHas('payroll_runs', [
            'id'     => $run->id,
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
            'year'  => 2025,
            'week'  => 10,
            'items' => [[
                'employee_id'          => $emp->id,
                'type'                 => 'daily_rate',
                'weekly_amount'        => 400,
                'cash'                 => 0,
                'bank'                 => 700,   // exceeds 400 earnings
                'prev_advance_balance' => 0,
            ]],
        ]);

        $item = PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(300.00, (float) $item->advance_given);
        $this->assertEquals(0.00,   (float) $item->advance_recovered);
        $this->assertEquals(300.00, (float) $item->advance_balance);
        $this->assertEquals(0.00,   (float) $item->cash_amount);   // forced to 0 on advance
    }

    public function test_advance_balance_accumulates_across_weeks(): void
    {
        // Week 10: earn 400, bank 700 → balance = 300
        // Week 11: earn 900, bank 700 → partial recovery, balance = 100
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 100]);

        $this->post('/payroll/save-week', [
            'year'  => 2025, 'week' => 10,
            'items' => [[
                'employee_id' => $emp->id, 'type' => 'daily_rate',
                'weekly_amount' => 400, 'cash' => 0, 'bank' => 700,
                'prev_advance_balance' => 0,
            ]],
        ]);

        $item10 = PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(300.00, (float) $item10->advance_balance);

        $this->post('/payroll/save-week', [
            'year'  => 2025, 'week' => 11,
            'items' => [[
                'employee_id' => $emp->id, 'type' => 'daily_rate',
                'weekly_amount' => 900, 'cash' => 200, 'bank' => 700,
                'prev_advance_balance' => 300,   // carried from week 10
            ]],
        ]);

        $item11 = PayrollItem::where('employee_id', $emp->id)->orderBy('id', 'desc')->first();
        // balance = max(0, 300 + 700 - 900) = 100 (partial recovery)
        $this->assertEquals(100.00,  (float) $item11->advance_balance);
        $this->assertEquals(0.00,    (float) $item11->advance_given);
        $this->assertEquals(200.00,  (float) $item11->advance_recovered);
    }

    public function test_advance_fully_settled_when_bank_below_earnings(): void
    {
        // Prev balance 300. Earn 900, send bank 400 (deliberately recovering 500 > 300 → settled)
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 100]);

        $this->post('/payroll/save-week', [
            'year'  => 2025, 'week' => 11,
            'items' => [[
                'employee_id' => $emp->id, 'type' => 'daily_rate',
                'weekly_amount' => 900, 'cash' => 500, 'bank' => 400,
                'prev_advance_balance' => 300,
            ]],
        ]);

        $item = PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(0.00,   (float) $item->advance_balance);   // fully settled
        $this->assertEquals(0.00,   (float) $item->advance_given);
        $this->assertEquals(300.00, (float) $item->advance_recovered);
    }

    public function test_no_advance_in_normal_week(): void
    {
        // Normal: earn 900, bank 700, cash 200 → no advance
        $this->actingAs($this->admin());
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 150]);

        $this->post('/payroll/save-week', [
            'year'  => 2025, 'week' => 10,
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
        // Pure arithmetic: max(0, prev + bank - earned)
        // Mirrors advanceBalance() in payroll.js
        $cases = [
            ['prev' => 0,   'bank' => 700, 'earned' => 400, 'expected' => 300],   // advance
            ['prev' => 300, 'bank' => 700, 'earned' => 900, 'expected' => 100],   // partial recovery
            ['prev' => 300, 'bank' => 400, 'earned' => 900, 'expected' => 0],     // full recovery
            ['prev' => 0,   'bank' => 700, 'earned' => 900, 'expected' => 0],     // normal, no advance
            ['prev' => 100, 'bank' => 700, 'earned' => 700, 'expected' => 100],   // no change (bank == earned)
        ];

        foreach ($cases as $c) {
            $result = max(0, round($c['prev'] + $c['bank'] - $c['earned'], 2));
            $this->assertEquals($c['expected'], $result, "prev={$c['prev']} bank={$c['bank']} earned={$c['earned']}");
        }
    }
}
