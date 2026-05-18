<?php

namespace Tests\Feature;

use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\HourlyAttendance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerDashboardTest extends TestCase
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
        return Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500]);
    }

    private function hourlyEmployee(): Employee
    {
        return Employee::factory()->create(['type' => 'hourly', 'hourly_rate' => 80, 'hours_per_day' => 8]);
    }

    private function assign(User $manager, Employee $employee, User $admin): void
    {
        $manager->assignedEmployees()->attach($employee->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);
    }

    // ── Admin dashboard unchanged ─────────────────────────────────────────────

    public function test_admin_sees_standard_dashboard(): void
    {
        $this->actingAs($this->admin())
            ->get('/')
            ->assertOk()
            ->assertViewHas('isManagerView', false)
            ->assertViewHas('employeeStats')
            ->assertViewHas('payrollStats');
    }

    // ── Manager dashboard: view routing ───────────────────────────────────────

    public function test_manager_sees_manager_view(): void
    {
        $this->actingAs($this->manager())
            ->get('/')
            ->assertOk()
            ->assertViewHas('isManagerView', true);
    }

    public function test_manager_dashboard_passes_team_cards(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->dailyEmployee();

        $this->assign($manager, $employee, $admin);

        $this->actingAs($manager)
            ->get('/')
            ->assertOk()
            ->assertViewHas('teamCards', fn ($cards) => $cards->count() === 1);
    }

    public function test_manager_with_no_assignments_gets_empty_team_cards(): void
    {
        $manager = $this->manager();
        $this->dailyEmployee(); // exists but not assigned

        $this->actingAs($manager)
            ->get('/')
            ->assertOk()
            ->assertViewHas('teamCards', fn ($cards) => $cards->isEmpty());
    }

    // ── Card data: daily employee ─────────────────────────────────────────────

    public function test_daily_card_unmarked_when_no_attendance_record(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->dailyEmployee();

        $this->assign($manager, $employee, $admin);

        $response = $this->actingAs($manager)->get('/');
        $cards = $response->viewData('teamCards');
        $card = $cards->first();

        $this->assertEquals('daily', $card['type']);
        $this->assertFalse($card['marked']);
        $this->assertEquals([], $card['days_map']);
        $this->assertEquals(0, $card['present']);
    }

    public function test_daily_card_marked_when_attendance_exists(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->dailyEmployee();

        $this->assign($manager, $employee, $admin);

        DailyRateAttendance::create([
            'employee_id' => $employee->id,
            'year' => now()->year,
            'week_number' => now()->weekOfYear,
            'days_map' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
            'overtime_map' => [],
            'total_working_days' => 6,
            'present_days' => 5,
            'locked' => false,
        ]);

        $cards = $this->actingAs($manager)->get('/')
            ->viewData('teamCards');

        $card = $cards->first();

        $this->assertTrue($card['marked']);
        $this->assertEquals(5, $card['present']);
        $this->assertArrayHasKey('mon', $card['days_map']);
    }

    // ── Card data: hourly employee ────────────────────────────────────────────

    public function test_hourly_card_unmarked_when_no_attendance_record(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->hourlyEmployee();

        $this->assign($manager, $employee, $admin);

        $cards = $this->actingAs($manager)->get('/')->viewData('teamCards');
        $card = $cards->first();

        $this->assertEquals('hourly', $card['type']);
        $this->assertFalse($card['marked']);
        $this->assertEquals(0, $card['total_hours']);
    }

    public function test_hourly_card_marked_when_attendance_exists(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->hourlyEmployee();

        $this->assign($manager, $employee, $admin);

        HourlyAttendance::create([
            'employee_id' => $employee->id,
            'year' => now()->year,
            'week_number' => now()->weekOfYear,
            'hours_map' => ['mon' => 8, 'tue' => 8, 'wed' => 8, 'thu' => 8, 'fri' => 8, 'sat' => 0, 'sun' => 0],
            'ot_map' => ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
            'total_hours' => 40,
            'overtime_hours' => 0,
            'locked' => false,
        ]);

        $cards = $this->actingAs($manager)->get('/')->viewData('teamCards');
        $card = $cards->first();

        $this->assertTrue($card['marked']);
        $this->assertEquals(40, $card['total_hours']);
    }

    // ── markedCount / unmarkedCount ───────────────────────────────────────────

    public function test_marked_and_unmarked_counts_are_correct(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $marked = $this->dailyEmployee();
        $unmarked = $this->dailyEmployee();

        $this->assign($manager, $marked, $admin);
        $this->assign($manager, $unmarked, $admin);

        DailyRateAttendance::create([
            'employee_id' => $marked->id,
            'year' => now()->year,
            'week_number' => now()->weekOfYear,
            'days_map' => ['mon' => 1, 'tue' => 0, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
            'overtime_map' => [],
            'total_working_days' => 6,
            'present_days' => 1,
            'locked' => false,
        ]);

        $response = $this->actingAs($manager)->get('/');

        $response->assertViewHas('markedCount', 1)
            ->assertViewHas('unmarkedCount', 1);
    }

    // ── Only active employees appear ──────────────────────────────────────────

    public function test_inactive_assigned_employees_are_excluded(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $active = $this->dailyEmployee();
        $inactive = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500, 'is_active' => false]);

        $this->assign($manager, $active, $admin);
        $this->assign($manager, $inactive, $admin);

        $cards = $this->actingAs($manager)->get('/')->viewData('teamCards');

        $this->assertCount(1, $cards);
        $this->assertEquals($active->id, $cards->first()['employee']->id);
    }
}
