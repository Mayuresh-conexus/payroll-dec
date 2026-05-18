<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManagerAttendanceScopeTest extends TestCase
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

    private function dailyPayload(Employee $employee): array
    {
        return [
            'year' => 2025,
            'week' => 10,
            'attendance' => [
                $employee->id => [
                    'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
                ],
            ],
        ];
    }

    private function hourlyPayload(Employee $employee): array
    {
        return [
            'year' => 2025,
            'week' => 10,
            'attendance' => [
                $employee->id => [
                    'hours_map' => ['mon' => 8, 'tue' => 8, 'wed' => 8, 'thu' => 8, 'fri' => 8, 'sat' => 0, 'sun' => 0],
                    'ot_map' => ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
                ],
            ],
        ];
    }

    // ── EmployeePolicy ────────────────────────────────────────────────────────

    public function test_manager_can_view_attendance_for_assigned_employee(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->dailyEmployee();

        $this->assign($manager, $employee, $admin);

        $this->assertTrue($manager->can('viewAttendance', $employee));
    }

    public function test_manager_cannot_view_attendance_for_unassigned_employee(): void
    {
        $manager = $this->manager();
        $employee = $this->dailyEmployee();

        $this->assertFalse($manager->can('viewAttendance', $employee));
    }

    public function test_admin_can_view_attendance_for_any_employee(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();

        $this->assertTrue($admin->can('viewAttendance', $employee));
    }

    public function test_manager_can_edit_attendance_for_assigned_employee(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->dailyEmployee();

        $this->assign($manager, $employee, $admin);

        $this->assertTrue($manager->can('editAttendance', $employee));
    }

    public function test_manager_cannot_edit_attendance_for_unassigned_employee(): void
    {
        $manager = $this->manager();
        $employee = $this->dailyEmployee();

        $this->assertFalse($manager->can('editAttendance', $employee));
    }

    public function test_manager_can_view_their_own_assigned_employee(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->dailyEmployee();

        $this->assign($manager, $employee, $admin);

        $this->assertTrue($manager->can('view', $employee));
    }

    public function test_manager_cannot_view_unassigned_employee(): void
    {
        $manager = $this->manager();
        $employee = $this->dailyEmployee();

        $this->assertFalse($manager->can('view', $employee));
    }

    // ── AttendanceController: daily-rate store ────────────────────────────────

    public function test_manager_can_save_daily_attendance_for_assigned_employee(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->dailyEmployee();

        $this->assign($manager, $employee, $admin);

        $this->actingAs($manager)
            ->post('/attendance/daily-rate', $this->dailyPayload($employee))
            ->assertRedirect();

        $this->assertDatabaseHas('daily_rate_attendances', [
            'employee_id' => $employee->id,
            'year' => 2025,
            'week_number' => 10,
        ]);
    }

    public function test_manager_cannot_save_daily_attendance_for_unassigned_employee(): void
    {
        $manager = $this->manager();
        $employee = $this->dailyEmployee();

        $this->actingAs($manager)
            ->post('/attendance/daily-rate', $this->dailyPayload($employee))
            ->assertForbidden();
    }

    // ── AttendanceController: hourly store ────────────────────────────────────

    public function test_manager_can_save_hourly_attendance_for_assigned_employee(): void
    {
        $admin = $this->admin();
        $manager = $this->manager();
        $employee = $this->hourlyEmployee();

        $this->assign($manager, $employee, $admin);

        $this->actingAs($manager)
            ->post('/attendance/hourly', $this->hourlyPayload($employee))
            ->assertRedirect();

        $this->assertDatabaseHas('hourly_attendances', [
            'employee_id' => $employee->id,
            'year' => 2025,
            'week_number' => 10,
        ]);
    }

    public function test_manager_cannot_save_hourly_attendance_for_unassigned_employee(): void
    {
        $manager = $this->manager();
        $employee = $this->hourlyEmployee();

        $this->actingAs($manager)
            ->post('/attendance/hourly', $this->hourlyPayload($employee))
            ->assertForbidden();
    }

    // ── AttendanceController: combined store ──────────────────────────────────

    public function test_manager_cannot_use_combined_save_for_unassigned_employee(): void
    {
        $manager = $this->manager();
        $employee = $this->dailyEmployee();

        $this->actingAs($manager)
            ->post('/attendance/save', $this->dailyPayload($employee))
            ->assertForbidden();
    }

    // ── Edge cases ────────────────────────────────────────────────────────────

    public function test_staff_cannot_access_attendance_page(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);

        $this->actingAs($staff)
            ->get('/attendance')
            ->assertForbidden();
    }

    public function test_admin_can_save_daily_attendance_for_any_employee(): void
    {
        $admin = $this->admin();
        $employee = $this->dailyEmployee();

        $this->actingAs($admin)
            ->post('/attendance/daily-rate', $this->dailyPayload($employee))
            ->assertRedirect();
    }

    public function test_manager_with_no_assignments_sees_empty_attendance_page(): void
    {
        $manager = $this->manager();
        $this->dailyEmployee(); // exists but not assigned to anyone

        $this->actingAs($manager)
            ->get('/attendance')
            ->assertOk();
    }
}
