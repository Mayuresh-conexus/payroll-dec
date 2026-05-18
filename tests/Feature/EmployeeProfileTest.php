<?php

namespace Tests\Feature;

use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeProfileTest extends TestCase
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

    private function staff(): User
    {
        return User::factory()->create(['role' => 'staff']);
    }

    private function dailyEmployee(): Employee
    {
        return Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 600]);
    }

    private function hourlyEmployee(): Employee
    {
        return Employee::factory()->create(['type' => 'hourly', 'hourly_rate' => 90, 'hours_per_day' => 8]);
    }

    private function assign(User $manager, Employee $employee, User $admin): void
    {
        $manager->assignedEmployees()->attach($employee->id, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);
    }

    // ── Access control ────────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $employee = $this->dailyEmployee();

        $this->get(route('employees.show', $employee))
            ->assertRedirect(route('login'));
    }

    public function test_staff_cannot_view_employee_profile(): void
    {
        $employee = $this->dailyEmployee();

        $this->actingAs($this->staff())
            ->get(route('employees.show', $employee))
            ->assertForbidden();
    }

    public function test_admin_can_view_any_employee_profile(): void
    {
        $employee = $this->dailyEmployee();

        $this->actingAs($this->admin())
            ->get(route('employees.show', $employee))
            ->assertOk();
    }

    public function test_manager_can_view_assigned_employee_profile(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->dailyEmployee();
        $this->assign($manager, $employee, $admin);

        $this->actingAs($manager)
            ->get(route('employees.show', $employee))
            ->assertOk();
    }

    public function test_manager_cannot_view_unassigned_employee_profile(): void
    {
        $manager = $this->manager();
        $employee = $this->dailyEmployee(); // not assigned

        $this->actingAs($manager)
            ->get(route('employees.show', $employee))
            ->assertForbidden();
    }

    // ── View data ─────────────────────────────────────────────────────────────

    public function test_profile_page_passes_employee_to_view(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();

        $response = $this->actingAs($admin)
            ->get(route('employees.show', $employee));

        $response->assertOk()
            ->assertViewHas('employee', fn ($e) => $e->id === $employee->id);
    }

    public function test_profile_page_passes_rate_history_to_view(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();

        $response = $this->actingAs($admin)
            ->get(route('employees.show', $employee));

        $response->assertOk()
            ->assertViewHas('rateHistory')
            ->assertViewHas('creators');
    }

    public function test_profile_page_passes_attendance_history_to_view(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();

        DailyRateAttendance::create([
            'employee_id' => $employee->id,
            'year' => now()->year,
            'week_number' => now()->weekOfYear,
            'days_map' => ['mon' => 1, 'tue' => 1, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
            'overtime_map' => [],
            'total_working_days' => 6,
            'present_days' => 2,
            'locked' => false,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('employees.show', $employee));

        $response->assertOk()
            ->assertViewHas('attendanceHistory', function ($history) {
                return $history->isNotEmpty()
                    && $history->first()['type'] === 'daily';
            });
    }

    public function test_hourly_employee_attendance_history_has_correct_type(): void
    {
        $admin = $this->admin();
        $employee = $this->hourlyEmployee();

        $response = $this->actingAs($admin)
            ->get(route('employees.show', $employee));

        $response->assertOk()
            ->assertViewHas('attendanceHistory', function ($history) {
                return $history->isNotEmpty()
                    && $history->first()['type'] === 'hourly';
            });
    }

    public function test_attendance_history_contains_eight_weeks(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();

        $response = $this->actingAs($admin)
            ->get(route('employees.show', $employee));

        $response->assertOk()
            ->assertViewHas('attendanceHistory', fn ($h) => $h->count() === 8);
    }

    public function test_profile_page_passes_audit_logs_to_view(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();

        $response = $this->actingAs($admin)
            ->get(route('employees.show', $employee));

        $response->assertOk()
            ->assertViewHas('auditLogs');
    }

    public function test_profile_page_passes_payroll_history_to_view(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();

        $response = $this->actingAs($admin)
            ->get(route('employees.show', $employee));

        $response->assertOk()
            ->assertViewHas('payrollHistory');
    }

    // ── View renders employee details ─────────────────────────────────────────

    public function test_profile_page_shows_employee_name(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();

        $this->actingAs($admin)
            ->get(route('employees.show', $employee))
            ->assertSee($employee->name);
    }

    public function test_profile_page_shows_employee_code(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();

        $this->actingAs($admin)
            ->get(route('employees.show', $employee))
            ->assertSee($employee->employee_code);
    }

    // ── Deleted employee returns 404 ──────────────────────────────────────────

    public function test_soft_deleted_employee_returns_404(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();
        $id = $employee->id;
        $employee->delete();

        $this->actingAs($admin)
            ->get(route('employees.show', $id))
            ->assertNotFound();
    }
}
