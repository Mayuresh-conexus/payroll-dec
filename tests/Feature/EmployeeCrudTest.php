<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCrudTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function test_admin_can_create_daily_rate_employee(): void
    {
        $this->actingAs($this->admin());

        $this->post('/employees', [
            'employee_code' => 'E001',
            'name'          => 'John Doe',
            'type'          => 'daily_rate',
            'daily_rate'    => '450',
        ])->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'employee_code' => 'E001',
            'name'          => 'John Doe',
            'type'          => 'daily_rate',
        ]);
    }

    public function test_admin_can_create_hourly_employee(): void
    {
        $this->actingAs($this->admin());

        $this->post('/employees', [
            'employee_code' => 'E002',
            'name'          => 'Jane Doe',
            'type'          => 'hourly',
            'hourly_rate'   => '75',
            'hours_per_day' => '8',
        ])->assertRedirect();

        $this->assertDatabaseHas('employees', [
            'employee_code' => 'E002',
            'type'          => 'hourly',
        ]);
    }

    public function test_employee_code_must_be_unique(): void
    {
        $this->actingAs($this->admin());

        Employee::factory()->create(['employee_code' => 'E001', 'type' => 'daily_rate']);

        $this->post('/employees', [
            'employee_code' => 'E001',
            'name'          => 'Another Person',
            'type'          => 'daily_rate',
            'daily_rate'    => '500',
        ])->assertSessionHasErrors('employee_code');
    }

    public function test_name_is_required(): void
    {
        $this->actingAs($this->admin());

        $this->post('/employees', [
            'employee_code' => 'E003',
            'type'          => 'daily_rate',
            'daily_rate'    => '400',
        ])->assertSessionHasErrors('name');
    }

    public function test_bank_fields_are_optional(): void
    {
        // Regression guard for BUG-04: bank_transfer_fix_amount was required
        $this->actingAs($this->admin());

        $this->post('/employees', [
            'employee_code' => 'E010',
            'name'          => 'No Bank',
            'type'          => 'daily_rate',
            'daily_rate'    => '300',
            // deliberately omit bank_name, bank_account, bank_ifsc, bank_transfer_fix_amount
        ])->assertSessionHasNoErrors();
    }

    // ── Update ───────────────────────────────────────────────────────────────

    public function test_admin_can_update_employee_name(): void
    {
        $this->actingAs($this->admin());

        $emp = Employee::factory()->create(['type' => 'daily_rate']);

        $this->patch("/employees/{$emp->id}", [
            'employee_code' => $emp->employee_code,
            'name'          => 'Updated Name',
            'type'          => 'daily_rate',
            'daily_rate'    => $emp->daily_rate,
        ])->assertRedirect()
          ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employees', [
            'id'   => $emp->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_employee_type_cannot_be_changed(): void
    {
        // Regression guard: type change must be blocked in controller
        $this->actingAs($this->admin());

        $emp = Employee::factory()->create(['type' => 'daily_rate']);

        // Send correct type but check the controller guards it
        // The controller checks: if ($request->input('type') !== $employee->type) → error
        $response = $this->patch("/employees/{$emp->id}", [
            'employee_code' => $emp->employee_code,
            'name'          => $emp->name,
            'type'          => 'hourly',   // ← attempting change
            'hourly_rate'   => 50,         // provide required_if fields so only type error fires
            'hours_per_day' => 8,
        ]);

        // The controller redirects back with a 'type' error
        $response->assertSessionHasErrors('type');

        // Employee type must remain unchanged
        $this->assertDatabaseHas('employees', [
            'id'   => $emp->id,
            'type' => 'daily_rate',
        ]);
    }

    // ── Soft delete ───────────────────────────────────────────────────────────

    public function test_deleted_employee_is_soft_deleted(): void
    {
        $this->actingAs($this->admin());

        $emp = Employee::factory()->create(['type' => 'daily_rate']);

        $this->delete("/employees/{$emp->id}")->assertRedirect();

        $this->assertSoftDeleted('employees', ['id' => $emp->id]);
    }

    public function test_deactivated_employee_still_in_db(): void
    {
        $this->actingAs($this->admin());

        $emp = Employee::factory()->create(['type' => 'daily_rate', 'is_active' => false]);

        $this->assertDatabaseHas('employees', ['id' => $emp->id, 'is_active' => false]);
    }

    // ── Search / filter ───────────────────────────────────────────────────────

    public function test_search_filter_returns_matching_employees(): void
    {
        $this->actingAs($this->admin());

        Employee::factory()->create(['name' => 'Ramu Kaka', 'type' => 'daily_rate']);
        Employee::factory()->create(['name' => 'Shyam Lal', 'type' => 'daily_rate']);

        $this->get('/employees?search=Ramu')
            ->assertOk()
            ->assertSee('Ramu Kaka')
            ->assertDontSee('Shyam Lal');
    }

    public function test_type_filter_returns_only_daily_rate(): void
    {
        $this->actingAs($this->admin());

        Employee::factory()->create(['name' => 'Daily Worker', 'type' => 'daily_rate']);
        Employee::factory()->create(['name' => 'Hourly Worker', 'type' => 'hourly']);

        $this->get('/employees?type=daily_rate')
            ->assertOk()
            ->assertSee('Daily Worker')
            ->assertDontSee('Hourly Worker');
    }
}
