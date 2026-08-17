<?php

namespace Tests\Feature;

use App\Models\Holiday;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HolidayManagementTest extends TestCase
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

    // ── Access control ───────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/holidays')->assertRedirect('/login');
    }

    public function test_manager_cannot_access_holidays_page(): void
    {
        $this->actingAs($this->manager());

        $this->get('/holidays')->assertForbidden();
    }

    public function test_manager_cannot_create_holiday(): void
    {
        $this->actingAs($this->manager());

        $this->post('/holidays', ['name' => 'Sneaky', 'start_date' => '2026-07-27'])->assertForbidden();
    }

    public function test_admin_can_view_holidays_page(): void
    {
        $this->actingAs($this->admin());

        $this->get('/holidays')->assertOk()->assertViewHas('holidays');
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function test_a_start_date_alone_creates_a_single_day_holiday(): void
    {
        // The form only requires a start date; the end date mirrors it so a
        // single-day holiday needs one field rather than two identical ones.
        $this->actingAs($this->admin());

        $this->post('/holidays', [
            'name' => 'St Patrick\'s Day',
            'start_date' => '2026-03-17',
        ])->assertRedirect();

        $holiday = Holiday::where('name', 'St Patrick\'s Day')->firstOrFail();

        $this->assertSame('2026-03-17', $holiday->start_date->toDateString());
        $this->assertSame('2026-03-17', $holiday->end_date->toDateString(), 'end date mirrors the start');
    }

    public function test_admin_can_create_a_multi_day_holiday(): void
    {
        $this->actingAs($this->admin());

        $this->post('/holidays', [
            'name' => 'Christmas Break',
            'start_date' => '2026-12-24',
            'end_date' => '2026-12-28',
        ])->assertRedirect();

        $holiday = Holiday::where('name', 'Christmas Break')->firstOrFail();

        $this->assertSame('2026-12-24', $holiday->start_date->toDateString());
        $this->assertSame('2026-12-28', $holiday->end_date->toDateString());
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $this->actingAs($this->admin());

        $this->post('/holidays', [
            'name' => 'Backwards',
            'start_date' => '2026-05-10',
            'end_date' => '2026-05-01',
        ])->assertSessionHasErrors('end_date');

        $this->assertDatabaseMissing('holidays', ['name' => 'Backwards']);
    }

    public function test_name_is_required(): void
    {
        $this->actingAs($this->admin());

        $this->post('/holidays', ['start_date' => '2026-05-10'])->assertSessionHasErrors('name');
    }

    // ── Update / delete ──────────────────────────────────────────────────────

    public function test_admin_can_update_a_holiday(): void
    {
        $this->actingAs($this->admin());
        $holiday = Holiday::factory()->on('2026-06-01')->create(['name' => 'June Bank Holiday']);

        $this->put("/holidays/{$holiday->id}", [
            'name' => 'June BH (moved)',
            'start_date' => '2026-06-02',
            'end_date' => '2026-06-02',
        ])->assertRedirect();

        $this->assertDatabaseHas('holidays', ['id' => $holiday->id, 'name' => 'June BH (moved)']);
    }

    public function test_admin_can_delete_a_holiday(): void
    {
        $this->actingAs($this->admin());
        $holiday = Holiday::factory()->on('2026-06-01')->create();

        $this->delete("/holidays/{$holiday->id}")->assertRedirect();

        $this->assertSoftDeleted('holidays', ['id' => $holiday->id]);
    }

    public function test_a_holiday_inside_a_finalised_week_cannot_be_changed(): void
    {
        // Changing the dates would silently rewrite pay for a week that has been
        // signed off, so the week has to be reopened first.
        $this->actingAs($this->admin());
        $holiday = Holiday::factory()->on('2026-07-27')->create(); // ISO week 31 of 2026

        PayrollRun::create([
            'year' => 2026,
            'week_number' => 31,
            'period_type' => 'weekly',
            'status' => 'final',
            'created_by' => auth()->id(),
            'generated_at' => now(),
        ]);

        $this->put("/holidays/{$holiday->id}", [
            'name' => 'Moved',
            'start_date' => '2026-07-28',
            'end_date' => '2026-07-28',
        ])->assertSessionHasErrors('holiday');

        $this->delete("/holidays/{$holiday->id}")->assertSessionHasErrors('holiday');

        $this->assertDatabaseHas('holidays', ['id' => $holiday->id, 'deleted_at' => null]);
    }

    // ── mapForWeek ───────────────────────────────────────────────────────────

    public function test_map_for_week_returns_only_days_inside_the_week(): void
    {
        // Week 31 of 2026 runs Mon 27 Jul .. Sun 2 Aug.
        Holiday::factory()->on('2026-07-24', '2026-07-28')->create(['name' => 'Spanning']);
        Holiday::factory()->on('2026-12-25')->create(['name' => 'Far Away']);

        $map = Holiday::mapForWeek(\Carbon\Carbon::create(2026, 7, 27));

        $this->assertSame(['mon' => 'Spanning', 'tue' => 'Spanning'], $map);
    }

    public function test_overlapping_holidays_yield_one_entry_per_day(): void
    {
        // Two records covering the same date must not pay the premium twice.
        Holiday::factory()->on('2026-07-27', '2026-07-29')->create(['name' => 'First']);
        Holiday::factory()->on('2026-07-28', '2026-07-30')->create(['name' => 'Second']);

        $map = Holiday::mapForWeek(\Carbon\Carbon::create(2026, 7, 27));

        $this->assertCount(4, $map, 'mon..thu covered once each, not five entries');
        $this->assertSame(['mon', 'tue', 'wed', 'thu'], array_keys($map));
    }

    public function test_deleted_holidays_are_ignored(): void
    {
        $holiday = Holiday::factory()->on('2026-07-27')->create();
        $holiday->delete();

        $this->assertSame([], Holiday::mapForWeek(\Carbon\Carbon::create(2026, 7, 27)));
    }
}
