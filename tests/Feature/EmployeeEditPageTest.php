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

    // ── Rate history timeline ───────────────────────────────────────────────

    public function test_timeline_shows_increase_with_percentage(): void
    {
        $employee = $this->dailyEmployee();
        $employee->rates()->create(['rate_type' => 'daily_rate', 'amount' => 500, 'effective_from' => '2026-01-01']);
        $employee->rates()->create(['rate_type' => 'daily_rate', 'amount' => 600, 'effective_from' => '2026-06-01']);

        $this->actingAs($this->admin())
            ->get(route('employees.edit', $employee))
            ->assertSee('€500.00', false)   // superseded amount
            ->assertSee('€600.00', false)   // new amount
            ->assertSee('20.0%', false)     // (600 - 500) / 500
            ->assertSee('bg-emerald-50 text-emerald-700', false); // upward styling
    }

    public function test_timeline_shows_decrease_with_percentage(): void
    {
        $employee = $this->dailyEmployee();
        $employee->rates()->create(['rate_type' => 'daily_rate', 'amount' => 600, 'effective_from' => '2026-01-01']);
        $employee->rates()->create(['rate_type' => 'daily_rate', 'amount' => 500, 'effective_from' => '2026-06-01']);

        $this->actingAs($this->admin())
            ->get(route('employees.edit', $employee))
            ->assertSee('16.7%', false)  // (500 - 600) / 600
            ->assertSee('bg-rose-50 text-rose-700', false); // downward styling
    }

    public function test_timeline_labels_a_single_entry_as_initial_rate(): void
    {
        // The reported case: with only one entry there is no prior rate to compare
        // against, so the row must say so rather than silently render a bare amount.
        $employee = $this->dailyEmployee();
        $employee->rates()->create(['rate_type' => 'daily_rate', 'amount' => 2000, 'effective_from' => '2026-08-11']);

        $this->actingAs($this->admin())
            ->get(route('employees.edit', $employee))
            ->assertSee('Initial rate')
            ->assertDontSee('No change');
    }

    public function test_timeline_shows_hours_per_day_change_without_currency(): void
    {
        $employee = $this->hourlyEmployee();
        $employee->rates()->create(['rate_type' => 'hours_per_day', 'amount' => 7, 'effective_from' => '2026-01-01']);
        $employee->rates()->create(['rate_type' => 'hours_per_day', 'amount' => 8, 'effective_from' => '2026-06-01']);

        $this->actingAs($this->admin())
            ->get(route('employees.edit', $employee))
            ->assertSee('7.0 hrs')
            ->assertSee('8.0 hrs')
            ->assertSee('14.3%'); // (8 - 7) / 7
    }

    public function test_current_badge_lands_on_right_entry_for_each_rate_type_when_interleaved(): void
    {
        // Regression guard: $ratesByType comes from groupBy(), which preserves each
        // row's original index in the full mixed-rate-type history rather than
        // reindexing per group. Without ->values() in the view, a tab whose first
        // entry isn't also the very first row overall would never show the "Current"
        // badge or highlighted dot on its true most-recent entry.
        $employee = $this->hourlyEmployee();
        $employee->rates()->create(['rate_type' => 'hourly_rate', 'amount' => 150, 'effective_from' => '2026-01-01']);
        $employee->rates()->create(['rate_type' => 'hours_per_day', 'amount' => 7, 'effective_from' => '2026-03-01']);
        $employee->rates()->create(['rate_type' => 'hourly_rate', 'amount' => 180, 'effective_from' => '2026-06-01']);
        $employee->rates()->create(['rate_type' => 'hours_per_day', 'amount' => 8, 'effective_from' => '2026-08-10']);

        $response = $this->actingAs($this->admin())
            ->get(route('employees.edit', $employee));

        $response->assertOk();
        $this->assertSame(
            2,
            substr_count($response->getContent(), '<span class="ml-1 text-[10px] font-semibold text-brand-600">Current</span>'),
            'Expected exactly one "Current" badge per rate-type tab (2 tabs for an hourly employee).'
        );
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
