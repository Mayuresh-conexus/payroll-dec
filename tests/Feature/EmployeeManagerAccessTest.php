<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeManagerAccessTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function employee(string $name = 'Someone'): Employee
    {
        return Employee::factory()->create(['name' => $name, 'type' => 'daily_rate', 'is_active' => true]);
    }

    // ── Access control ───────────────────────────────────────────────────────

    public function test_manager_cannot_change_manager_access(): void
    {
        $employee = $this->employee();

        $this->actingAs(User::factory()->create(['role' => 'manager']))
            ->put("/employees/{$employee->id}/manager-access", [
                'email' => 'x@example.com',
                'password' => 'password123',
                'password_confirmation' => 'password123',
                'employee_ids' => [$this->employee('Other')->id],
            ])->assertForbidden();
    }

    // ── Granting ─────────────────────────────────────────────────────────────

    public function test_granting_creates_a_manager_login_and_assigns_the_team(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->employee('Boss');
        $reportA = $this->employee('Report A');
        $reportB = $this->employee('Report B');

        $this->put("/employees/{$employee->id}/manager-access", [
            'email' => 'boss@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'employee_ids' => [$reportA->id, $reportB->id],
        ])->assertSessionHasNoErrors();

        $manager = User::where('email', 'boss@example.com')->first();
        $this->assertNotNull($manager);
        $this->assertSame('manager', $manager->role);
        $this->assertSame($employee->id, $manager->employee_id);
        $this->assertEqualsCanonicalizing(
            [$reportA->id, $reportB->id],
            $manager->assignedEmployees()->pluck('employees.id')->all()
        );
    }

    public function test_granting_requires_at_least_one_employee(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->employee('Boss');

        $this->put("/employees/{$employee->id}/manager-access", [
            'email' => 'boss@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'employee_ids' => [],
        ])->assertSessionHasErrors('employee_ids');

        $this->assertDatabaseMissing('users', ['email' => 'boss@example.com']);
    }

    public function test_granting_requires_a_password_for_a_new_account(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->employee('Boss');

        $this->put("/employees/{$employee->id}/manager-access", [
            'email' => 'boss@example.com',
            'employee_ids' => [$this->employee('Report')->id],
        ])->assertSessionHasErrors('password');
    }

    public function test_cannot_steal_an_employee_from_another_manager(): void
    {
        $this->actingAs($this->admin());
        $otherBoss = User::factory()->create(['role' => 'manager']);
        $taken = $this->employee('Taken');
        $otherBoss->assignedEmployees()->attach($taken->id, ['assigned_by' => 1, 'assigned_at' => now()]);

        $employee = $this->employee('Boss');

        $this->put("/employees/{$employee->id}/manager-access", [
            'email' => 'boss@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'employee_ids' => [$taken->id],
        ])->assertSessionHasErrors('employee_ids');

        $this->assertEquals(
            [$otherBoss->id],
            $taken->managers()->pluck('users.id')->all(),
            'the original manager keeps the employee'
        );

        // A rejected request must not leave a login behind with no team.
        $this->assertDatabaseMissing('users', ['email' => 'boss@example.com']);
    }

    // ── Editing / revoking ───────────────────────────────────────────────────

    public function test_blank_password_keeps_the_existing_one(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->employee('Boss');
        $manager = User::factory()->create([
            'role' => 'manager', 'employee_id' => $employee->id, 'email' => 'boss@example.com',
        ]);
        $originalHash = $manager->password;

        $this->put("/employees/{$employee->id}/manager-access", [
            'email' => 'newboss@example.com',
            'employee_ids' => [$this->employee('Report')->id],
        ])->assertSessionHasNoErrors();

        $manager->refresh();
        $this->assertSame('newboss@example.com', $manager->email);
        $this->assertSame($originalHash, $manager->password);
    }

    public function test_revoking_deletes_the_login_and_frees_the_team(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->employee('Boss');
        $manager = User::factory()->create(['role' => 'manager', 'employee_id' => $employee->id]);
        $report = $this->employee('Report');
        $manager->assignedEmployees()->attach($report->id, ['assigned_by' => 1, 'assigned_at' => now()]);

        $this->put("/employees/{$employee->id}/manager-access", ['revoke' => 1])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('users', ['id' => $manager->id]);
        $this->assertCount(0, $report->fresh()->managers, 'team is released back to the pool');
    }

    // ── Candidate list ───────────────────────────────────────────────────────

    public function test_edit_page_only_offers_unassigned_employees(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->employee('Boss');
        $free = $this->employee('Free Agent');
        $taken = $this->employee('Already Managed');

        $otherBoss = User::factory()->create(['role' => 'manager']);
        $otherBoss->assignedEmployees()->attach($taken->id, ['assigned_by' => 1, 'assigned_at' => now()]);

        $response = $this->get(route('employees.edit', $employee));

        $response->assertOk()->assertViewHas('selectableEmployees', function ($list) use ($free, $taken, $employee) {
            $ids = $list->pluck('id')->all();

            return in_array($free->id, $ids, true)
                && ! in_array($taken->id, $ids, true)
                && ! in_array($employee->id, $ids, true); // never offer the employee themselves
        });
    }

    public function test_own_team_stays_selectable_when_editing_an_existing_manager(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->employee('Boss');
        $manager = User::factory()->create(['role' => 'manager', 'employee_id' => $employee->id]);
        $mine = $this->employee('My Report');
        $manager->assignedEmployees()->attach($mine->id, ['assigned_by' => 1, 'assigned_at' => now()]);

        $this->get(route('employees.edit', $employee))
            ->assertOk()
            ->assertViewHas('selectableEmployees', fn ($list) => in_array($mine->id, $list->pluck('id')->all(), true))
            ->assertViewHas('assignedEmployeeIds', fn ($ids) => in_array($mine->id, $ids, true));
    }
}
