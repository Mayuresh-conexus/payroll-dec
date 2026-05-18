<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerAssignmentTest extends TestCase
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

    private function employee(): Employee
    {
        return Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500]);
    }

    // ── index ─────────────────────────────────────────────────────────────────

    public function test_admin_can_view_manager_assignments_page(): void
    {
        $this->actingAs($this->admin())
            ->get('/managers')
            ->assertOk()
            ->assertViewIs('managers.index');
    }

    public function test_manager_cannot_view_manager_assignments_page(): void
    {
        $this->actingAs($this->manager())
            ->get('/managers')
            ->assertForbidden();
    }

    public function test_staff_cannot_view_manager_assignments_page(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get('/managers')
            ->assertForbidden();
    }

    public function test_index_lists_managers_with_their_assigned_employees(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->employee();

        $manager->assignedEmployees()->attach($employee->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get('/managers');

        $response->assertOk()
            ->assertViewHas('managers', fn ($managers) => $managers->contains('id', $manager->id))
            ->assertViewHas('allEmployees', fn ($employees) => $employees->contains('id', $employee->id));
    }

    // ── assign ────────────────────────────────────────────────────────────────

    public function test_admin_can_assign_employee_to_manager(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->employee();

        $this->actingAs($admin)
            ->post("/managers/{$manager->id}/assign", ['employee_ids' => [$employee->id]])
            ->assertRedirect();

        $this->assertDatabaseHas('manager_employee', [
            'manager_id' => $manager->id,
            'employee_id' => $employee->id,
            'assigned_by' => $admin->id,
        ]);
    }

    public function test_assigning_replaces_previous_team(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee1 = $this->employee();
        $employee2 = $this->employee();

        // Initial: employee1 assigned
        $manager->assignedEmployees()->attach($employee1->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        // Sync: now assign only employee2
        $this->actingAs($admin)
            ->post("/managers/{$manager->id}/assign", ['employee_ids' => [$employee2->id]])
            ->assertRedirect();

        $this->assertDatabaseMissing('manager_employee', ['employee_id' => $employee1->id]);
        $this->assertDatabaseHas('manager_employee', ['employee_id' => $employee2->id]);
    }

    public function test_assigning_empty_list_removes_all_team_members(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->employee();

        $manager->assignedEmployees()->attach($employee->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post("/managers/{$manager->id}/assign", [])
            ->assertRedirect();

        $this->assertDatabaseMissing('manager_employee', ['manager_id' => $manager->id]);
    }

    public function test_cannot_assign_employee_already_owned_by_different_manager(): void
    {
        $admin = $this->admin();
        $manager1 = $this->manager();
        $manager2 = $this->manager();
        $employee = $this->employee();

        $manager1->assignedEmployees()->attach($employee->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($admin)
            ->post("/managers/{$manager2->id}/assign", ['employee_ids' => [$employee->id]])
            ->assertRedirect()
            ->assertSessionHasErrors('employee_ids');

        // employee still belongs to manager1
        $this->assertDatabaseHas('manager_employee', [
            'manager_id' => $manager1->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_cannot_assign_to_non_manager_user(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['role' => 'staff']);
        $employee = $this->employee();

        $this->actingAs($admin)
            ->post("/managers/{$staff->id}/assign", ['employee_ids' => [$employee->id]])
            ->assertStatus(422);
    }

    public function test_non_admin_cannot_assign_employees(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();

        $this->actingAs($manager)
            ->post("/managers/{$manager->id}/assign", ['employee_ids' => [$employee->id]])
            ->assertForbidden();
    }

    // ── unassign ──────────────────────────────────────────────────────────────

    public function test_admin_can_unassign_employee_from_manager(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->employee();

        $manager->assignedEmployees()->attach($employee->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($admin)
            ->delete("/managers/{$manager->id}/employees/{$employee->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('manager_employee', [
            'manager_id' => $manager->id,
            'employee_id' => $employee->id,
        ]);
    }

    public function test_non_admin_cannot_unassign_employees(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->employee();

        $manager->assignedEmployees()->attach($employee->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->actingAs($manager)
            ->delete("/managers/{$manager->id}/employees/{$employee->id}")
            ->assertForbidden();
    }

    public function test_unassign_on_non_manager_user_returns_422(): void
    {
        $admin = $this->admin();
        $staff = User::factory()->create(['role' => 'staff']);
        $employee = $this->employee();

        $this->actingAs($admin)
            ->delete("/managers/{$staff->id}/employees/{$employee->id}")
            ->assertStatus(422);
    }
}
