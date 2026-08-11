<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeEditPageTest extends TestCase
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

    private function hourlyEmployee(): Employee
    {
        return Employee::factory()->create(['type' => 'hourly', 'hourly_rate' => 90, 'hours_per_day' => 8]);
    }

    // ── Access control ───────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $employee = $this->dailyEmployee();

        $this->get(route('employees.edit', $employee))
            ->assertRedirect(route('login'));
    }

    public function test_manager_cannot_view_edit_page(): void
    {
        $employee = $this->dailyEmployee();

        $this->actingAs($this->manager())
            ->get(route('employees.edit', $employee))
            ->assertForbidden();
    }

    public function test_admin_can_view_edit_page(): void
    {
        $employee = $this->dailyEmployee();

        $this->actingAs($this->admin())
            ->get(route('employees.edit', $employee))
            ->assertOk();
    }

    // ── Page content ─────────────────────────────────────────────────────────

    public function test_edit_page_shows_employee_current_values(): void
    {
        $employee = $this->dailyEmployee();

        $this->actingAs($this->admin())
            ->get(route('employees.edit', $employee))
            ->assertSee($employee->name)
            ->assertSee($employee->employee_code);
    }

    public function test_edit_page_shows_one_tab_for_daily_rate_employee(): void
    {
        $employee = $this->dailyEmployee();

        $this->actingAs($this->admin())
            ->get(route('employees.edit', $employee))
            ->assertSee('Daily Rate')
            ->assertDontSee('Hourly Rate')
            ->assertDontSee('Hours / Day');
    }

    public function test_edit_page_shows_two_tabs_for_hourly_employee(): void
    {
        $employee = $this->hourlyEmployee();

        $this->actingAs($this->admin())
            ->get(route('employees.edit', $employee))
            ->assertSee('Hourly Rate')
            ->assertSee('Hours / Day')
            ->assertDontSee('Daily Rate');
    }

    // ── Update still works from the new page ────────────────────────────────

    public function test_invalid_update_redirects_back_to_edit_page_with_errors(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();

        $response = $this->actingAs($admin)
            ->from(route('employees.edit', $employee))
            ->patch(route('employees.update', $employee), [
                'employee_code' => $employee->employee_code,
                'name' => $employee->name,
                'type' => 'hourly', // attempting to change the locked type
                'hourly_rate' => 50,
                'hours_per_day' => 8,
            ]);

        $response->assertRedirect(route('employees.edit', $employee));
        $response->assertSessionHasErrors('type');
    }

    // ── Soft-deleted employee ────────────────────────────────────────────────

    public function test_soft_deleted_employee_edit_page_returns_404(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();
        $id = $employee->id;
        $employee->delete();

        $this->actingAs($admin)
            ->get(route('employees.edit', $id))
            ->assertNotFound();
    }
}
