<?php

namespace Tests\Feature;

use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\HourlyAttendance;
use App\Models\PayrollItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceStoreTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function dailyEmployee(): Employee
    {
        return Employee::factory()->create([
            'type'       => 'daily_rate',
            'daily_rate' => 500,
        ]);
    }

    private function hourlyEmployee(): Employee
    {
        return Employee::factory()->create([
            'type'          => 'hourly',
            'hourly_rate'   => 80,
            'hours_per_day' => 8,
        ]);
    }

    // ── Daily-rate attendance ─────────────────────────────────────────────────

    public function test_saving_daily_attendance_creates_db_record(): void
    {
        $this->actingAs($this->admin());
        $emp = $this->dailyEmployee();

        $this->post('/attendance/daily-rate', [
            'year'       => 2025,
            'week'       => 2,
            'attendance' => [
                $emp->id => [
                    'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 0, 'sun' => 0],
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('daily_rate_attendances', [
            'employee_id' => $emp->id,
            'year'        => 2025,
            'week_number' => 2,
            'present_days' => 5,
        ]);
    }

    public function test_saving_daily_attendance_creates_payroll_item(): void
    {
        $this->actingAs($this->admin());
        $emp = $this->dailyEmployee();

        $this->post('/attendance/daily-rate', [
            'year'       => 2025,
            'week'       => 3,
            'attendance' => [
                $emp->id => [
                    'days' => ['mon' => 1, 'tue' => 1, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
                ],
            ],
        ]);

        $this->assertDatabaseHas('payroll_items', [
            'employee_id'  => $emp->id,
            'type'         => 'daily_rate',
            'present_days' => 2,
        ]);
    }

    // ── Hourly attendance ─────────────────────────────────────────────────────

    public function test_saving_hourly_attendance_creates_db_record(): void
    {
        $this->actingAs($this->admin());
        $emp = $this->hourlyEmployee();

        $this->post('/attendance/hourly', [
            'year'       => 2025,
            'week'       => 4,
            'attendance' => [
                $emp->id => [
                    'hours_map' => ['mon' => 8, 'tue' => 8, 'wed' => 8, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
                    'ot_map'    => [],
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('hourly_attendances', [
            'employee_id' => $emp->id,
            'year'        => 2025,
            'week_number' => 4,
        ]);
    }

    public function test_saving_hourly_attendance_creates_payroll_item(): void
    {
        $this->actingAs($this->admin());
        $emp = $this->hourlyEmployee();

        $this->post('/attendance/hourly', [
            'year'       => 2025,
            'week'       => 5,
            'attendance' => [
                $emp->id => [
                    'hours_map' => ['mon' => 8, 'tue' => 8, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
                    'ot_map'    => [],
                ],
            ],
        ]);

        $this->assertDatabaseHas('payroll_items', [
            'employee_id' => $emp->id,
            'type'        => 'hourly',
        ]);
    }

    // ── Week 53 regression guard (BUG-02) ────────────────────────────────────

    public function test_week_53_is_accepted(): void
    {
        // Regression guard: max was incorrectly set to 52 before the fix
        $this->actingAs($this->admin());
        $emp = $this->dailyEmployee();

        $this->post('/attendance/daily-rate', [
            'year'       => 2020,   // 2020 had 53 ISO weeks
            'week'       => 53,
            'attendance' => [
                $emp->id => [
                    'days' => ['mon' => 1, 'tue' => 0, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
                ],
            ],
        ])->assertSessionHasNoErrors();
    }

    // ── Locking ──────────────────────────────────────────────────────────────

    public function test_lock_week_sets_locked_flag(): void
    {
        $this->actingAs($this->admin());
        $emp = $this->dailyEmployee();

        $this->post('/attendance/daily-rate', [
            'year'      => 2025,
            'week'      => 6,
            'lock_week' => '1',
            'attendance' => [
                $emp->id => [
                    'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
                ],
            ],
        ]);

        $this->assertDatabaseHas('daily_rate_attendances', [
            'employee_id' => $emp->id,
            'year'        => 2025,
            'week_number' => 6,
            'locked'      => true,
        ]);
    }

    // ── Combined store ────────────────────────────────────────────────────────

    public function test_combined_store_handles_mixed_employee_types(): void
    {
        $this->actingAs($this->admin());
        $daily  = $this->dailyEmployee();
        $hourly = $this->hourlyEmployee();

        $this->post('/attendance/save', [
            'year' => 2025,
            'week' => 7,
            'attendance' => [
                $daily->id => [
                    'days'         => ['mon' => 1, 'tue' => 1, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
                    'overtime_map' => [],
                ],
                $hourly->id => [
                    'hours_map' => ['mon' => 8, 'tue' => 8, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
                    'ot_map'    => [],
                ],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('daily_rate_attendances', ['employee_id' => $daily->id, 'week_number' => 7]);
        $this->assertDatabaseHas('hourly_attendances',     ['employee_id' => $hourly->id, 'week_number' => 7]);
    }
}
