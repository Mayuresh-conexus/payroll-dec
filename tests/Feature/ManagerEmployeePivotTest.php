<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerEmployeePivotTest extends TestCase
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

    // ── assignedEmployees relationship ────────────────────────────────────────

    public function test_manager_can_have_employees_assigned(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();
        $admin = $this->admin();

        $manager->assignedEmployees()->attach($employee->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->assertCount(1, $manager->assignedEmployees);
        $this->assertTrue($manager->assignedEmployees->contains($employee));
    }

    public function test_manager_can_have_multiple_employees_assigned(): void
    {
        $manager = $this->manager();
        $admin = $this->admin();
        $employees = Employee::factory()->count(3)->create(['type' => 'daily_rate', 'daily_rate' => 500]);

        foreach ($employees as $employee) {
            $manager->assignedEmployees()->attach($employee->id, [
                'assigned_by' => $admin->id,
                'assigned_at' => now(),
            ]);
        }

        $this->assertCount(3, $manager->fresh()->assignedEmployees);
    }

    // ── managers relationship on Employee ─────────────────────────────────────

    public function test_employee_exposes_its_manager_via_managers_relationship(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();
        $admin = $this->admin();

        $manager->assignedEmployees()->attach($employee->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($employee->fresh()->managers->contains($manager));
    }

    // ── forManager scope ──────────────────────────────────────────────────────

    public function test_for_manager_scope_returns_only_assigned_employees(): void
    {
        $manager = $this->manager();
        $admin = $this->admin();
        $assigned = $this->employee();
        $notAssigned = $this->employee();

        $manager->assignedEmployees()->attach($assigned->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $result = Employee::forManager($manager->id)->get();

        $this->assertTrue($result->contains($assigned));
        $this->assertFalse($result->contains($notAssigned));
    }

    public function test_for_manager_scope_returns_empty_when_no_assignments(): void
    {
        $manager = $this->manager();
        $this->employee(); // exists but unassigned

        $result = Employee::forManager($manager->id)->get();

        $this->assertCount(0, $result);
    }

    // ── pivot unique constraint ───────────────────────────────────────────────

    public function test_employee_can_only_be_assigned_to_one_manager(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        $admin = $this->admin();
        $manager1 = $this->manager();
        $manager2 = $this->manager();
        $employee = $this->employee();

        $manager1->assignedEmployees()->attach($employee->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        // Second attach on same employee_id violates the unique constraint
        $manager2->assignedEmployees()->attach($employee->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);
    }

    // ── cascade delete ────────────────────────────────────────────────────────

    public function test_pivot_row_is_removed_when_employee_is_force_deleted(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();
        $admin = $this->admin();

        $manager->assignedEmployees()->attach($employee->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->assertDatabaseHas('manager_employee', ['employee_id' => $employee->id]);

        $employee->forceDelete();

        $this->assertDatabaseMissing('manager_employee', ['employee_id' => $employee->id]);
    }

    public function test_pivot_rows_are_removed_when_manager_user_is_deleted(): void
    {
        $manager = $this->manager();
        $employee = $this->employee();
        $admin = $this->admin();

        $manager->assignedEmployees()->attach($employee->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->assertDatabaseHas('manager_employee', ['manager_id' => $manager->id]);

        $manager->delete();

        $this->assertDatabaseMissing('manager_employee', ['manager_id' => $manager->id]);
    }
}
