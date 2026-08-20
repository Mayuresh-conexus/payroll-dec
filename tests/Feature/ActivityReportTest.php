<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_separates_people_by_the_address_they_worked_from(): void
    {
        // The point of the report: telling the office apart from a developer,
        // when both have been in the same database.
        $office = User::factory()->create(['name' => 'Dublin Office', 'role' => 'admin']);
        $developer = User::factory()->create(['name' => 'Developer', 'role' => 'admin']);

        AuditLog::create(['user_id' => $office->id, 'action' => 'updated', 'model_type' => 'Employee', 'model_id' => 1, 'ip_address' => '86.40.1.1']);
        AuditLog::create(['user_id' => $developer->id, 'action' => 'updated', 'model_type' => 'Employee', 'model_id' => 1, 'ip_address' => '49.36.2.2']);

        $this->artisan('activity:report --days=1')
            ->expectsOutputToContain('Dublin Office')
            ->expectsOutputToContain('86.40.1.1')
            ->expectsOutputToContain('Developer')
            ->expectsOutputToContain('49.36.2.2')
            ->assertSuccessful();
    }

    public function test_it_reports_attendance_weeks_that_were_touched(): void
    {
        DailyRateAttendance::create([
            'employee_id' => Employee::factory()->create()->id,
            'year' => 2026, 'week_number' => 31,
            'days_map' => ['mon' => 1], 'present_days' => 1, 'total_working_days' => 6,
        ]);

        $this->artisan('activity:report --days=1')
            ->expectsOutputToContain('week 31 of 2026')
            ->assertSuccessful();
    }

    public function test_marking_attendance_now_records_who_did_it(): void
    {
        // Attendance was the one thing not attributed, which is exactly the
        // question this report exists to answer.
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user);

        app(\App\Services\AttendanceService::class)->saveDailyEmployee(
            Employee::factory()->create()->id,
            ['days' => ['mon' => 1, 'tue' => 1]],
            2026, 31, false
        );

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'model_type' => 'DailyRateAttendance',
            'action' => 'created',
        ]);
    }

    public function test_a_quiet_window_says_so_rather_than_printing_nothing(): void
    {
        $this->artisan('activity:report --days=1')
            ->expectsOutputToContain('nobody')
            ->assertSuccessful();
    }
}
