<?php

namespace Tests\Unit;

use App\Models\Employee;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceServiceTest extends TestCase
{
    use RefreshDatabase;

    private AttendanceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AttendanceService::class);
    }

    // ── saveDailyEmployee ─────────────────────────────────────────────────────

    public function test_save_daily_employee_creates_attendance_record(): void
    {
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 450]);

        $row = [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ];

        $this->service->saveDailyEmployee($emp->id, $row, 2025, 30, false);

        $this->assertDatabaseHas('daily_rate_attendances', [
            'employee_id'  => $emp->id,
            'week_number'  => 30,
            'present_days' => 3,
        ]);
    }

    public function test_save_daily_employee_present_days_sum_is_correct(): void
    {
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500]);

        $row = [
            'days' => ['mon' => 1, 'tue' => 1, 'wed' => 1, 'thu' => 1, 'fri' => 1, 'sat' => 1, 'sun' => 0],
        ];

        $this->service->saveDailyEmployee($emp->id, $row, 2025, 31, false);

        $att = \App\Models\DailyRateAttendance::where('employee_id', $emp->id)
            ->where('week_number', 31)
            ->first();

        $this->assertEquals(6, $att->present_days);
    }

    public function test_overtime_amount_is_stored_and_included_in_gross(): void
    {
        $emp = Employee::factory()->create(['type' => 'daily_rate', 'daily_rate' => 500]);

        $row = [
            'days'         => ['mon' => 1, 'tue' => 1, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
            'overtime_map' => ['mon' => 200, 'tue' => 0, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
        ];

        $this->service->saveDailyEmployee($emp->id, $row, 2025, 32, false);

        $this->assertDatabaseHas('daily_rate_attendances', [
            'employee_id'    => $emp->id,
            'week_number'    => 32,
            'overtime_amount' => 200,
        ]);

        // payroll item gross should include overtime
        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        // 2 days × 500 + 200 OT = 1200
        $this->assertEquals(1200.0, $item->gross_amount);
    }

    // ── saveHourlyEmployee ────────────────────────────────────────────────────

    public function test_save_hourly_employee_creates_attendance_record(): void
    {
        $emp = Employee::factory()->create([
            'type'          => 'hourly',
            'hourly_rate'   => 80,
            'hours_per_day' => 8,
        ]);

        $row = [
            'hours_map' => ['mon' => 8, 'tue' => 8, 'wed' => 8, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
            'ot_map'    => [],
        ];

        $this->service->saveHourlyEmployee($emp->id, $row, 2025, 33, false);

        $this->assertDatabaseHas('hourly_attendances', [
            'employee_id' => $emp->id,
            'week_number' => 33,
            'total_hours' => 24,    // 3 days × 8 hours
        ]);
    }

    public function test_save_hourly_gross_equals_hours_times_rate(): void
    {
        $emp = Employee::factory()->create([
            'type'          => 'hourly',
            'hourly_rate'   => 100,
            'hours_per_day' => 8,
        ]);

        $row = [
            'hours_map' => ['mon' => 8, 'tue' => 8, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
            'ot_map'    => [],
        ];

        $this->service->saveHourlyEmployee($emp->id, $row, 2025, 34, false);

        $item = \App\Models\PayrollItem::where('employee_id', $emp->id)->first();
        $this->assertEquals(1600.0, $item->gross_amount); // 16h × 100
    }
}
