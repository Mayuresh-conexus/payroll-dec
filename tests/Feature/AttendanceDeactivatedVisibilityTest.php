<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceDeactivatedVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    /** Deactivated on Tue 11 Aug 2026. W33 = Mon 10 – Sun 16 Aug. */
    private function leaver(): Employee
    {
        return Employee::factory()->create([
            'name' => 'Leaver Person',
            'type' => 'daily_rate',
            'daily_rate' => 500,
            'is_active' => false,
            'deactivated_at' => '2026-08-11',
        ]);
    }

    public function test_visible_in_the_week_they_left_because_some_days_were_worked(): void
    {
        $this->actingAs($this->admin());
        $this->leaver();

        $this->get('/attendance?year=2026&week=33')
            ->assertOk()
            ->assertSee('Leaver Person')
            ->assertSee('Inactive 11 Aug 2026'); // flagged, not hidden
    }

    public function test_visible_in_earlier_weeks_they_actually_worked(): void
    {
        $this->actingAs($this->admin());
        $this->leaver();

        // W30 and W32 both end before the deactivation date.
        $this->get('/attendance?year=2026&week=30')->assertOk()->assertSee('Leaver Person');
        $this->get('/attendance?year=2026&week=32')->assertOk()->assertSee('Leaver Person');
    }

    public function test_hidden_from_weeks_starting_after_they_left(): void
    {
        $this->actingAs($this->admin());
        $this->leaver();

        // W34 starts Mon 17 Aug — entirely after the cut-off.
        $this->get('/attendance?year=2026&week=34')->assertOk()->assertDontSee('Leaver Person');
        $this->get('/attendance?year=2026&week=40')->assertOk()->assertDontSee('Leaver Person');
    }

    public function test_only_days_before_the_cutoff_stay_markable_in_the_leaving_week(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->leaver();

        $html = $this->get('/attendance?year=2026&week=33')->getContent();
        $row = $this->extractRow($html, $employee->id);

        // Mon 10 Aug is still employed -> a real toggle input exists.
        $this->assertStringContainsString("attendance[{$employee->id}][days][mon]", $row);

        // Tue 11 Aug onward are locked out -> no inputs that could be submitted.
        foreach (['tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
            $this->assertStringNotContainsString("attendance[{$employee->id}][days][{$day}]", $row);
        }
        $this->assertStringContainsString('Not employed on this date', $row);
    }

    public function test_active_employee_keeps_every_day_markable(): void
    {
        $this->actingAs($this->admin());
        $employee = Employee::factory()->create([
            'name' => 'Still Here', 'type' => 'daily_rate', 'daily_rate' => 500, 'is_active' => true,
        ]);

        $row = $this->extractRow($this->get('/attendance?year=2026&week=33')->getContent(), $employee->id);

        foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
            $this->assertStringContainsString("attendance[{$employee->id}][days][{$day}]", $row);
        }
    }

    public function test_legacy_deactivated_employee_without_a_date_stays_hidden(): void
    {
        // No cut-off recorded means we cannot tell which weeks they worked, so the
        // safe old behaviour (hidden) is kept rather than guessing.
        $this->actingAs($this->admin());
        Employee::factory()->create([
            'name' => 'Legacy Leaver', 'type' => 'daily_rate', 'is_active' => false, 'deactivated_at' => null,
        ]);

        $this->get('/attendance?year=2026&week=33')->assertOk()->assertDontSee('Legacy Leaver');
    }

    private function extractRow(string $html, int $employeeId): string
    {
        $start = strpos($html, 'data-employee="'.$employeeId.'"');
        $this->assertNotFalse($start, "row for employee {$employeeId} not found");
        $end = strpos($html, '</tr>', $start);

        return substr($html, $start, $end - $start);
    }
}
