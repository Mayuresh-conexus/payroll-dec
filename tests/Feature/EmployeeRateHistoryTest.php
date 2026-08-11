<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeRateHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function dailyEmployee(): Employee
    {
        return Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 600]);
    }

    // ── Delete ───────────────────────────────────────────────────────────────

    public function test_admin_can_delete_a_rate_history_entry(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();
        $rate = $employee->rates()->create([
            'rate_type' => 'daily_rate',
            'amount' => 500,
            'effective_from' => '2026-01-01',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('employees.rates.destroy', [$employee, $rate]))
            ->assertRedirect();

        $this->assertSoftDeleted('employee_rates', ['id' => $rate->id]);
    }

    public function test_deleting_rate_writes_audit_log(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();
        $rate = $employee->rates()->create([
            'rate_type' => 'daily_rate',
            'amount' => 500,
            'effective_from' => '2026-01-01',
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->delete(route('employees.rates.destroy', [$employee, $rate]));

        $this->assertDatabaseHas('audit_logs', [
            'model_type' => 'EmployeeRate',
            'model_id' => $rate->id,
            'action' => 'deleted',
        ]);
    }

    public function test_manager_cannot_delete_rate_history_entry(): void
    {
        $employee = $this->dailyEmployee();
        $rate = $employee->rates()->create([
            'rate_type' => 'daily_rate',
            'amount' => 500,
            'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($this->manager())
            ->delete(route('employees.rates.destroy', [$employee, $rate]))
            ->assertForbidden();

        $this->assertDatabaseHas('employee_rates', ['id' => $rate->id, 'deleted_at' => null]);
    }

    public function test_deleting_rate_from_different_employee_via_url_returns_404(): void
    {
        $admin = $this->admin();
        $employeeA = $this->dailyEmployee();
        $employeeB = $this->dailyEmployee();
        $rateOfB = $employeeB->rates()->create([
            'rate_type' => 'daily_rate',
            'amount' => 500,
            'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($admin)
            ->delete(route('employees.rates.destroy', [$employeeA, $rateOfB]))
            ->assertNotFound();

        $this->assertDatabaseHas('employee_rates', ['id' => $rateOfB->id, 'deleted_at' => null]);
    }

    // ── Denormalized column recompute ───────────────────────────────────────

    public function test_deleting_latest_rate_recomputes_employee_current_rate(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 600]);

        $employee->rates()->create([
            'rate_type' => 'daily_rate', 'amount' => 500, 'effective_from' => '2026-01-01',
        ]);
        $newest = $employee->rates()->create([
            'rate_type' => 'daily_rate', 'amount' => 600, 'effective_from' => '2026-06-01',
        ]);

        $this->actingAs($admin)
            ->delete(route('employees.rates.destroy', [$employee, $newest]));

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'daily_rate' => 500]);
    }

    public function test_deleting_only_rate_of_a_type_leaves_employee_column_untouched(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 600]);

        $onlyRate = $employee->rates()->create([
            'rate_type' => 'daily_rate', 'amount' => 600, 'effective_from' => '2026-01-01',
        ]);

        $this->actingAs($admin)
            ->delete(route('employees.rates.destroy', [$employee, $onlyRate]));

        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'daily_rate' => 600]);
    }

    public function test_deleting_a_rate_does_not_affect_other_employees_rates(): void
    {
        $admin = $this->admin();
        $employeeA = $this->dailyEmployee();
        $employeeB = $this->dailyEmployee();

        $rateA = $employeeA->rates()->create(['rate_type' => 'daily_rate', 'amount' => 500, 'effective_from' => '2026-01-01']);
        $rateB = $employeeB->rates()->create(['rate_type' => 'daily_rate', 'amount' => 700, 'effective_from' => '2026-01-01']);

        $this->actingAs($admin)
            ->delete(route('employees.rates.destroy', [$employeeA, $rateA]));

        $this->assertSoftDeleted('employee_rates', ['id' => $rateA->id]);
        $this->assertDatabaseHas('employee_rates', ['id' => $rateB->id, 'deleted_at' => null]);
    }
}
