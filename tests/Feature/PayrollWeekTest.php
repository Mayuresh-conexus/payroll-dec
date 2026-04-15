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
}
