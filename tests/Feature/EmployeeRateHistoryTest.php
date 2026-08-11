<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\EmployeeRate;
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

    // ── Effective date handling on save ─────────────────────────────────────

    public function test_chosen_effective_date_is_stored_not_today(): void
    {
        $admin = $this->admin();
        $this->actingAs($admin);
        $employee = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 1000]);

        $this->patch("/employees/{$employee->id}", [
            'employee_code' => $employee->employee_code,
            'name' => $employee->name,
            'type' => 'daily_rate',
            'daily_rate' => 1500,
            'rate_effective_from' => '2026-03-15',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employee_rates', [
            'employee_id' => $employee->id,
            'amount' => 1500,
            'effective_from' => '2026-03-15',
        ]);
    }

    public function test_blank_effective_date_falls_back_to_today_not_null(): void
    {
        // A blank date input arrives as null (ConvertEmptyStringsToNull). Storing that
        // null would make the row sort last in every effective_from lookup, so the new
        // rate would never take effect.
        $admin = $this->admin();
        $this->actingAs($admin);
        $employee = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 1000]);

        $this->patch("/employees/{$employee->id}", [
            'employee_code' => $employee->employee_code,
            'name' => $employee->name,
            'type' => 'daily_rate',
            'daily_rate' => 1500,
            'rate_effective_from' => '',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('employee_rates', [
            'employee_id' => $employee->id,
            'amount' => 1500,
            'effective_from' => now()->toDateString(),
        ]);
        $this->assertDatabaseMissing('employee_rates', [
            'employee_id' => $employee->id,
            'effective_from' => null,
        ]);
    }

    public function test_saving_without_changing_the_rate_creates_no_history_row(): void
    {
        // Regression guard: the change check used a strict !== between the form string
        // ("1000") and the DB decimal string ("1000.00"), so every save appended a
        // bogus history entry even when the rate was untouched.
        $admin = $this->admin();
        $this->actingAs($admin);
        $employee = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 1000]);

        $this->patch("/employees/{$employee->id}", [
            'employee_code' => $employee->employee_code,
            'name' => 'Renamed Only',
            'type' => 'daily_rate',
            'daily_rate' => '1000',
        ])->assertSessionHasNoErrors();

        $this->assertSame(0, EmployeeRate::where('employee_id', $employee->id)->count());
    }

    public function test_backdated_rate_does_not_become_the_current_rate(): void
    {
        // The reported confusion: a backdated entry is recorded, but a later existing
        // rate still wins — and the denormalized column must agree with that, not with
        // whatever was typed into the form.
        $admin = $this->admin();
        $this->actingAs($admin);
        $employee = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 2000]);
        $employee->rates()->create([
            'rate_type' => 'daily_rate', 'amount' => 2000, 'effective_from' => '2026-08-11',
        ]);

        $this->patch("/employees/{$employee->id}", [
            'employee_code' => $employee->employee_code,
            'name' => $employee->name,
            'type' => 'daily_rate',
            'daily_rate' => 2500,
            'rate_effective_from' => '2026-05-01', // earlier than the existing entry
        ])->assertSessionHasNoErrors();

        // The backdated entry is recorded in history...
        $this->assertDatabaseHas('employee_rates', [
            'employee_id' => $employee->id, 'amount' => 2500, 'effective_from' => '2026-05-01',
        ]);
        // ...but the rate actually in effect is still the later one, and the
        // denormalized column agrees rather than advertising 2500.
        $this->assertEquals(2000.0, $employee->fresh()->latestRateOf('daily_rate'));
        $this->assertDatabaseHas('employees', ['id' => $employee->id, 'daily_rate' => 2000]);
    }

    // ── Future-dated (scheduled) rates ──────────────────────────────────────

    public function test_future_dated_rate_is_not_treated_as_the_current_rate(): void
    {
        // Reported bug: a rate scheduled to start next week was displayed as the
        // current rate. Only an entry whose effective_from has arrived counts.
        $employee = Employee::factory()->create(['type' => 'hourly', 'hourly_rate' => 250]);
        $employee->rates()->create(['rate_type' => 'hourly_rate', 'amount' => 200, 'effective_from' => now()->toDateString()]);
        $employee->rates()->create(['rate_type' => 'hourly_rate', 'amount' => 250, 'effective_from' => now()->addDays(9)->toDateString()]);

        $this->assertEquals(200.0, $employee->currentRateOf('hourly_rate'));
        $this->assertEquals(250.0, $employee->latestRateOf('hourly_rate'), 'latestRateOf still reports the newest entry on record');

        $upcoming = $employee->upcomingRateEntryOf('hourly_rate');
        $this->assertNotNull($upcoming);
        $this->assertEquals(250.0, (float) $upcoming->amount);
    }

    public function test_no_upcoming_entry_when_all_rates_have_started(): void
    {
        $employee = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 600]);
        $employee->rates()->create(['rate_type' => 'daily_rate', 'amount' => 500, 'effective_from' => now()->subMonth()->toDateString()]);
        $employee->rates()->create(['rate_type' => 'daily_rate', 'amount' => 600, 'effective_from' => now()->toDateString()]);

        $this->assertEquals(600.0, $employee->currentRateOf('daily_rate'));
        $this->assertNull($employee->upcomingRateEntryOf('daily_rate'));
    }

    public function test_edit_page_shows_current_rate_and_scheduled_change(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['type' => 'hourly', 'hourly_rate' => 250, 'hours_per_day' => 8]);
        $employee->rates()->create(['rate_type' => 'hourly_rate', 'amount' => 200, 'effective_from' => now()->toDateString()]);
        $employee->rates()->create(['rate_type' => 'hourly_rate', 'amount' => 250, 'effective_from' => now()->addDays(9)->toDateString()]);

        $response = $this->actingAs($admin)->get(route('employees.edit', $employee));

        $response->assertOk()
            ->assertSee('€200.00', false)                                        // current, not the scheduled 250
            ->assertSee('Changes to', false)                                     // upcoming note on the card
            ->assertSee(now()->addDays(9)->format('d M Y'))                      // when it starts
            ->assertSee('Scheduled');                                            // timeline badge on the future row
    }

    public function test_profile_page_shows_current_rate_not_future_rate(): void
    {
        $admin = $this->admin();
        $employee = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 900]);
        $employee->rates()->create(['rate_type' => 'daily_rate', 'amount' => 700, 'effective_from' => now()->toDateString()]);
        $employee->rates()->create(['rate_type' => 'daily_rate', 'amount' => 900, 'effective_from' => now()->addWeek()->toDateString()]);

        $this->actingAs($admin)
            ->get(route('employees.show', $employee))
            ->assertOk()
            ->assertSee('€700.00', false)   // the rate actually in effect
            ->assertSee('Scheduled');       // the future entry is flagged, not shown as current
    }

    public function test_payroll_still_uses_the_rate_effective_for_that_week(): void
    {
        // Guard the boundary that matters most: a rate scheduled for the future must
        // not be applied to payroll for a week before it starts.
        $employee = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 900]);
        $employee->rates()->create(['rate_type' => 'daily_rate', 'amount' => 700, 'effective_from' => '2026-01-01']);
        $employee->rates()->create(['rate_type' => 'daily_rate', 'amount' => 900, 'effective_from' => '2026-06-01']);

        $this->assertEquals(700.0, $employee->rateAt(\Carbon\Carbon::parse('2026-03-01'), 'daily_rate'));
        $this->assertEquals(900.0, $employee->rateAt(\Carbon\Carbon::parse('2026-07-01'), 'daily_rate'));
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
