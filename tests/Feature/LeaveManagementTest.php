<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\Leave;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use App\Services\AttendanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveManagementTest extends TestCase
{
    use RefreshDatabase;

    /** Week 31 of 2026 runs Mon 27 Jul .. Sun 2 Aug. */
    private const WEEK_YEAR = 2026;

    private const WEEK_NUMBER = 31;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function manager(): User
    {
        return User::factory()->create(['role' => 'manager']);
    }

    private function dailyEmployee(array $attributes = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'type' => 'daily_rate',
            'daily_rate' => 100,
            'weekly_active_days' => 5,
            'joining_date' => '2026-01-15',
        ], $attributes));
    }

    private function hourlyEmployee(array $attributes = []): Employee
    {
        return Employee::factory()->hourly()->create(array_merge([
            'hourly_rate' => 10,
            'hours_per_day' => 8,
            'weekly_active_days' => 5,
            'joining_date' => '2026-01-15',
        ], $attributes));
    }

    // ── Access control ───────────────────────────────────────────────────────

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/leaves')->assertRedirect('/login');
    }

    public function test_manager_cannot_access_leave_page(): void
    {
        $this->actingAs($this->manager());

        $this->get('/leaves')->assertForbidden();
    }

    public function test_manager_cannot_record_leave(): void
    {
        $this->actingAs($this->manager());
        $employee = $this->dailyEmployee();

        $this->post('/leaves', [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-27',
        ])->assertForbidden();
    }

    public function test_admin_can_view_the_leave_page(): void
    {
        $this->actingAs($this->admin());

        $this->get('/leaves')
            ->assertOk()
            ->assertViewHas('leaves')
            ->assertViewHas('balances');
    }

    // ── Create ───────────────────────────────────────────────────────────────

    public function test_a_start_date_alone_records_a_single_day(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->dailyEmployee();

        $this->post('/leaves', [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-27',
            'reason' => 'Annual leave',
        ])->assertRedirect();

        $leave = Leave::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame('2026-07-27', $leave->start_date->toDateString());
        $this->assertSame('2026-07-27', $leave->end_date->toDateString(), 'end date mirrors the start');
        $this->assertSame('Annual leave', $leave->reason);
    }

    public function test_admin_can_record_a_multi_day_leave(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->dailyEmployee();

        $this->post('/leaves', [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-27',
            'end_date' => '2026-07-31',
        ])->assertRedirect();

        $leave = Leave::where('employee_id', $employee->id)->firstOrFail();

        $this->assertSame('2026-07-27', $leave->start_date->toDateString());
        $this->assertSame('2026-07-31', $leave->end_date->toDateString());
    }

    public function test_hourly_leave_defaults_to_the_employees_standard_day(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->hourlyEmployee(['hours_per_day' => 7.5]);

        $this->post('/leaves', [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-27',
        ])->assertRedirect();

        $this->assertSame(7.5, Leave::where('employee_id', $employee->id)->firstOrFail()->hours_per_day);
    }

    public function test_a_shorter_leave_day_can_be_entered_by_hand(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->hourlyEmployee(['hours_per_day' => 8]);

        $this->post('/leaves', [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-27',
            'hours_per_day' => 4,
        ])->assertRedirect();

        $this->assertSame(4.0, Leave::where('employee_id', $employee->id)->firstOrFail()->hours_per_day);
    }

    public function test_daily_staff_never_carry_leave_hours(): void
    {
        // Daily-rate staff are paid whole days, so an hours figure would be noise.
        $this->actingAs($this->admin());
        $employee = $this->dailyEmployee();

        $this->post('/leaves', [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-27',
            'hours_per_day' => 8,
        ])->assertRedirect();

        $this->assertNull(Leave::where('employee_id', $employee->id)->firstOrFail()->hours_per_day);
    }

    // ── Validation ───────────────────────────────────────────────────────────

    public function test_an_employee_is_required(): void
    {
        $this->actingAs($this->admin());

        $this->post('/leaves', ['start_date' => '2026-07-27'])->assertSessionHasErrors('employee_id');
    }

    public function test_end_date_before_start_date_is_rejected(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->dailyEmployee();

        $this->post('/leaves', [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-31',
            'end_date' => '2026-07-27',
        ])->assertSessionHasErrors('end_date');

        $this->assertDatabaseCount('leaves', 0);
    }

    public function test_overlapping_leave_for_the_same_employee_is_rejected(): void
    {
        // Two records covering one day would deduct it from the balance twice.
        $this->actingAs($this->admin());
        $employee = $this->dailyEmployee();
        Leave::factory()->on('2026-07-27', '2026-07-29')->create(['employee_id' => $employee->id]);

        $this->post('/leaves', [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-29',
            'end_date' => '2026-07-31',
        ])->assertSessionHasErrors('start_date');

        $this->assertDatabaseCount('leaves', 1);
    }

    public function test_overlapping_leave_for_a_different_employee_is_allowed(): void
    {
        $this->actingAs($this->admin());
        $first = $this->dailyEmployee();
        $second = $this->dailyEmployee();
        Leave::factory()->on('2026-07-27', '2026-07-29')->create(['employee_id' => $first->id]);

        $this->post('/leaves', [
            'employee_id' => $second->id,
            'start_date' => '2026-07-27',
            'end_date' => '2026-07-29',
        ])->assertRedirect();

        $this->assertDatabaseCount('leaves', 2);
    }

    // ── Update / delete ──────────────────────────────────────────────────────

    public function test_admin_can_update_leave(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->dailyEmployee();
        $leave = Leave::factory()->on('2026-07-27')->create(['employee_id' => $employee->id]);

        $this->put("/leaves/{$leave->id}", [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-28',
            'end_date' => '2026-07-28',
            'reason' => 'Moved',
        ])->assertRedirect();

        $leave->refresh();

        $this->assertSame('2026-07-28', $leave->start_date->toDateString());
        $this->assertSame('Moved', $leave->reason);
    }

    public function test_leave_being_edited_does_not_clash_with_itself(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->dailyEmployee();
        $leave = Leave::factory()->on('2026-07-27', '2026-07-29')->create(['employee_id' => $employee->id]);

        $this->put("/leaves/{$leave->id}", [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-27',
            'end_date' => '2026-07-30',
        ])->assertSessionHasNoErrors();

        $this->assertSame('2026-07-30', $leave->refresh()->end_date->toDateString());
    }

    public function test_admin_can_delete_leave(): void
    {
        $this->actingAs($this->admin());
        $leave = Leave::factory()->on('2026-07-27')->create(['employee_id' => $this->dailyEmployee()->id]);

        $this->delete("/leaves/{$leave->id}")->assertRedirect();

        $this->assertSoftDeleted('leaves', ['id' => $leave->id]);
    }

    // ── Finalised payroll ────────────────────────────────────────────────────

    public function test_leave_inside_a_finalised_week_cannot_be_changed(): void
    {
        // Changing the dates would silently rewrite pay for a week that has been
        // signed off, so the week has to be reopened first.
        $this->actingAs($this->admin());
        $employee = $this->dailyEmployee();
        $leave = Leave::factory()->on('2026-07-27')->create(['employee_id' => $employee->id]);
        $this->finaliseWeek();

        $this->put("/leaves/{$leave->id}", [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-28',
            'end_date' => '2026-07-28',
        ])->assertSessionHasErrors('leave');

        $this->delete("/leaves/{$leave->id}")->assertSessionHasErrors('leave');

        $this->assertDatabaseHas('leaves', ['id' => $leave->id, 'deleted_at' => null]);
    }

    public function test_leave_cannot_be_recorded_inside_a_finalised_week(): void
    {
        // Adding pay to a signed-off week is the same problem as changing it.
        $this->actingAs($this->admin());
        $employee = $this->dailyEmployee();
        $this->finaliseWeek();

        $this->post('/leaves', [
            'employee_id' => $employee->id,
            'start_date' => '2026-07-27',
        ])->assertSessionHasErrors('leave');

        $this->assertDatabaseCount('leaves', 0);
    }

    // ── Payroll ──────────────────────────────────────────────────────────────

    public function test_daily_leave_is_paid_in_the_week_it_falls(): void
    {
        $employee = $this->dailyEmployee(['daily_rate' => 100]);
        Leave::factory()->on('2026-07-27', '2026-07-28')->create(['employee_id' => $employee->id]);

        app(AttendanceService::class)->saveDailyEmployee(
            $employee->id,
            ['days' => ['wed' => 1, 'thu' => 1, 'fri' => 1]],
            self::WEEK_YEAR,
            self::WEEK_NUMBER,
            false
        );

        $item = PayrollItem::where('employee_id', $employee->id)->firstOrFail();

        $this->assertEquals(2.0, (float) $item->leave_days);
        $this->assertEquals(200.0, (float) $item->leave_amount);
        // 3 days worked at 100 plus 2 days of leave at 100.
        $this->assertEquals(500.0, (float) $item->gross_amount);
    }

    public function test_hourly_leave_is_paid_in_the_week_it_falls(): void
    {
        $employee = $this->hourlyEmployee(['hourly_rate' => 10, 'hours_per_day' => 8]);
        Leave::factory()->on('2026-07-27', '2026-07-28')->create([
            'employee_id' => $employee->id,
            'hours_per_day' => 8,
        ]);

        app(AttendanceService::class)->saveHourlyEmployee(
            $employee->id,
            ['hours_map' => ['wed' => 8, 'thu' => 8, 'fri' => 8]],
            self::WEEK_YEAR,
            self::WEEK_NUMBER,
            false
        );

        $item = PayrollItem::where('employee_id', $employee->id)->firstOrFail();

        $this->assertEquals(16.0, (float) $item->leave_hours);
        $this->assertEquals(160.0, (float) $item->leave_amount);
        // 24 hours worked at 10 plus 16 hours of leave at 10.
        $this->assertEquals(400.0, (float) $item->gross_amount);
    }

    public function test_a_week_without_leave_carries_none(): void
    {
        $employee = $this->dailyEmployee(['daily_rate' => 100]);

        app(AttendanceService::class)->saveDailyEmployee(
            $employee->id,
            ['days' => ['mon' => 1, 'tue' => 1]],
            self::WEEK_YEAR,
            self::WEEK_NUMBER,
            false
        );

        $item = PayrollItem::where('employee_id', $employee->id)->firstOrFail();

        $this->assertEquals(0.0, (float) $item->leave_amount);
        $this->assertEquals(200.0, (float) $item->gross_amount);
    }

    public function test_the_weekly_payroll_page_shows_leave(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->dailyEmployee(['daily_rate' => 100]);
        Leave::factory()->on('2026-07-27', '2026-07-28')->create(['employee_id' => $employee->id]);

        app(AttendanceService::class)->saveDailyEmployee(
            $employee->id,
            ['days' => ['wed' => 1]],
            self::WEEK_YEAR,
            self::WEEK_NUMBER,
            false
        );

        $response = $this->get('/payroll?year='.self::WEEK_YEAR.'&week='.self::WEEK_NUMBER);

        $response->assertOk()->assertViewHas('showLeave', true);

        $row = $response->viewData('rows')->firstWhere('employee.id', $employee->id);

        $this->assertEquals(2.0, $row['leave_days']);
        $this->assertEquals(200.0, $row['leave_amount']);
        $this->assertEquals(300.0, $row['gross_amount']);
    }

    public function test_a_week_spent_entirely_on_leave_still_reaches_the_monthly_payroll(): void
    {
        // The monthly page splits each week's pay between months by the days it was
        // earned on. A week with no attendance at all used to count zero such days,
        // which would have dropped the leave pay on the floor.
        $this->actingAs($this->admin());
        $employee = $this->dailyEmployee(['daily_rate' => 100]);
        Leave::factory()->on('2026-07-27', '2026-07-31')->create(['employee_id' => $employee->id]);

        app(AttendanceService::class)->saveDailyEmployee(
            $employee->id,
            ['days' => []],
            self::WEEK_YEAR,
            self::WEEK_NUMBER,
            false
        );

        $response = $this->get('/payroll/monthly?month=2026-07');

        $response->assertOk();

        $row = $response->viewData('rows')->firstWhere('employee.id', $employee->id);

        // Mon–Fri of leave at 100 a day, all inside July.
        $this->assertEquals(500.0, round((float) $row['gross_amount'], 2));
    }

    public function test_the_payslip_shows_paid_leave_and_the_lines_add_up(): void
    {
        // The breakdown must reconcile: worked days + leave = total gross.
        $employee = $this->dailyEmployee(['daily_rate' => 100]);
        Leave::factory()->on('2026-07-27', '2026-07-28')->create(['employee_id' => $employee->id]);

        app(AttendanceService::class)->saveDailyEmployee(
            $employee->id,
            ['days' => ['wed' => 1, 'thu' => 1, 'fri' => 1]],
            self::WEEK_YEAR,
            self::WEEK_NUMBER,
            false
        );

        $item = PayrollItem::where('employee_id', $employee->id)->firstOrFail();
        $weekStart = \Carbon\Carbon::now()->setISODate(self::WEEK_YEAR, self::WEEK_NUMBER, 1);

        $html = view('payroll.payslip', [
            'year' => self::WEEK_YEAR,
            'week' => self::WEEK_NUMBER,
            'weekStart' => $weekStart,
            'weekEnd' => $weekStart->copy()->addDays(6),
            'run' => $item->run,
            'item' => $item,
            'employee' => $employee,
            'dailyAtt' => null,
            'hourlyAtt' => null,
            'generatedAt' => now(),
        ])->render();

        $this->assertStringContainsString('Paid leave', $html);
        $this->assertStringContainsString('300.00', $html, 'base = 3 days x 100');
        $this->assertStringContainsString('200.00', $html, 'leave = 2 days x 100');
        $this->assertStringContainsString('500.00', $html, 'total gross = 300 + 200');
    }

    // ── Attendance grid ──────────────────────────────────────────────────────

    public function test_a_leave_day_shows_l_on_the_attendance_grid(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->dailyEmployee();
        Leave::factory()->on('2026-07-28')->create(['employee_id' => $employee->id]); // Tuesday

        $html = $this->get('/attendance?year='.self::WEEK_YEAR.'&week='.self::WEEK_NUMBER)->getContent();

        $tuesdayCell = $this->extractDayCell($html, $employee->id, 'tue');
        $mondayCell = $this->extractDayCell($html, $employee->id, 'mon');

        // The P/A/L label is rendered client-side by Alpine (x-text), so the
        // server-rendered markup is asserted on the state it will render from.
        $this->assertStringContainsString("present ? 'P' : (onLeave ? 'L' : 'A')", $tuesdayCell);
        $this->assertStringContainsString('onLeave: true', $tuesdayCell);
        $this->assertStringContainsString('onLeave: false', $mondayCell, 'an ordinary day is unaffected');
    }

    public function test_a_day_already_marked_present_still_shows_p_over_leave(): void
    {
        $this->actingAs($this->admin());
        $employee = $this->dailyEmployee();
        Leave::factory()->on('2026-07-28')->create(['employee_id' => $employee->id]); // Tuesday

        app(AttendanceService::class)->saveDailyEmployee(
            $employee->id,
            ['days' => ['mon' => 1, 'tue' => 1]],
            self::WEEK_YEAR,
            self::WEEK_NUMBER,
            false
        );

        $html = $this->get('/attendance?year='.self::WEEK_YEAR.'&week='.self::WEEK_NUMBER)->getContent();
        $tuesdayCell = $this->extractDayCell($html, $employee->id, 'tue');

        $this->assertStringContainsString('present: true', $tuesdayCell);
        $this->assertStringContainsString('onLeave: true', $tuesdayCell, 'still flagged, in case the toggle is reverted');
    }

    private function extractDayCell(string $html, int $employeeId, string $day): string
    {
        $rowStart = strpos($html, 'data-employee="'.$employeeId.'"');
        $this->assertNotFalse($rowStart, 'employee row not found');

        $rowEnd = strpos($html, '</tr>', $rowStart);
        $row = substr($html, $rowStart, $rowEnd - $rowStart);

        $cellStart = strpos($row, 'data-day="'.$day.'"');
        $this->assertNotFalse($cellStart, "day cell {$day} not found");

        $cellEnd = strpos($row, '</td>', $cellStart);

        return substr($row, $cellStart, $cellEnd - $cellStart);
    }

    private function finaliseWeek(): void
    {
        PayrollRun::create([
            'year' => self::WEEK_YEAR,
            'week_number' => self::WEEK_NUMBER,
            'period_type' => 'weekly',
            'status' => 'final',
            'created_by' => auth()->id(),
            'generated_at' => now(),
        ]);
    }
}
