<?php

namespace Tests\Unit;

use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\HourlyAttendance;
use App\Models\Leave;
use App\Services\LeaveService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveServiceTest extends TestCase
{
    use RefreshDatabase;

    private LeaveService $service;

    /** Week 31 of 2026 runs Mon 27 Jul .. Sun 2 Aug. */
    private const WEEK_YEAR = 2026;

    private const WEEK_NUMBER = 31;

    /** A date inside the leave year of every employee built below. */
    private const TODAY = '2026-08-13';

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(LeaveService::class);
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

    // ── Entitlement ──────────────────────────────────────────────────────────

    public function test_daily_entitlement_is_four_times_the_working_week(): void
    {
        $fiveDayWeek = $this->dailyEmployee(['weekly_active_days' => 5]);
        $sixDayWeek = $this->dailyEmployee(['weekly_active_days' => 6]);

        $this->assertSame(20.0, $this->service->entitlementFor($fiveDayWeek, Carbon::parse(self::TODAY)));
        $this->assertSame(24.0, $this->service->entitlementFor($sixDayWeek, Carbon::parse(self::TODAY)));
    }

    public function test_daily_entitlement_assumes_a_six_day_week_when_none_is_set(): void
    {
        // Six is what the attendance grid already assumes, so an employee with no
        // working week on file is not quietly given a smaller entitlement.
        $employee = $this->dailyEmployee(['weekly_active_days' => null]);

        $this->assertSame(24.0, $this->service->entitlementFor($employee, Carbon::parse(self::TODAY)));
    }

    public function test_hourly_entitlement_is_eight_percent_of_hours_clocked(): void
    {
        $employee = $this->hourlyEmployee();
        $this->clockHours($employee, self::WEEK_YEAR, self::WEEK_NUMBER, ['mon' => 8, 'tue' => 8, 'wed' => 8, 'thu' => 8, 'fri' => 8]);

        // 40 hours clocked × 8%
        $this->assertSame(3.2, $this->service->entitlementFor($employee, Carbon::parse(self::TODAY)));
    }

    public function test_hours_clocked_before_the_leave_year_do_not_accrue(): void
    {
        // Week 2 of 2026 (5–11 Jan) falls before a 15 January joining anniversary.
        $employee = $this->hourlyEmployee();
        $this->clockHours($employee, 2026, 2, ['mon' => 8, 'tue' => 8]);

        $this->assertSame(0.0, $this->service->entitlementFor($employee, Carbon::parse(self::TODAY)));
    }

    // ── Leave year ───────────────────────────────────────────────────────────

    public function test_the_leave_year_runs_from_the_joining_anniversary(): void
    {
        $employee = $this->dailyEmployee(['joining_date' => '2025-03-10']);

        $balance = $this->service->balanceFor($employee, Carbon::parse(self::TODAY));

        $this->assertSame('2026-03-10', $balance['year_start']->toDateString());
        $this->assertSame('2027-03-09', $balance['year_end']->toDateString());
    }

    public function test_a_date_before_this_years_anniversary_belongs_to_the_previous_leave_year(): void
    {
        $employee = $this->dailyEmployee(['joining_date' => '2025-03-10']);

        $balance = $this->service->balanceFor($employee, Carbon::parse('2026-02-01'));

        $this->assertSame('2025-03-10', $balance['year_start']->toDateString());
    }

    public function test_an_employee_with_no_joining_date_falls_back_to_the_calendar_year(): void
    {
        $employee = $this->dailyEmployee(['joining_date' => null]);

        $balance = $this->service->balanceFor($employee, Carbon::parse(self::TODAY));

        $this->assertSame('2026-01-01', $balance['year_start']->toDateString());
        $this->assertSame('2026-12-31', $balance['year_end']->toDateString());
    }

    // ── Taken ────────────────────────────────────────────────────────────────

    public function test_only_working_days_are_deducted_from_a_daily_balance(): void
    {
        // A full week of leave for someone on a five-day week costs five days, not seven.
        $employee = $this->dailyEmployee(['weekly_active_days' => 5]);
        Leave::factory()->on('2026-07-27', '2026-08-02')->create(['employee_id' => $employee->id]);

        $this->assertSame(5.0, $this->service->takenFor($employee, Carbon::parse(self::TODAY)));
    }

    public function test_a_six_day_week_loses_its_saturday_to_leave_as_well(): void
    {
        $employee = $this->dailyEmployee(['weekly_active_days' => 6]);
        Leave::factory()->on('2026-07-27', '2026-08-02')->create(['employee_id' => $employee->id]);

        $this->assertSame(6.0, $this->service->takenFor($employee, Carbon::parse(self::TODAY)));
    }

    public function test_hourly_leave_is_deducted_in_hours(): void
    {
        $employee = $this->hourlyEmployee(['hours_per_day' => 8]);
        Leave::factory()->on('2026-07-27', '2026-07-29')->create([
            'employee_id' => $employee->id,
            'hours_per_day' => 8,
        ]);

        $this->assertSame(24.0, $this->service->takenFor($employee, Carbon::parse(self::TODAY)));
    }

    public function test_a_leave_record_can_carry_a_shorter_day_than_the_standard_one(): void
    {
        $employee = $this->hourlyEmployee(['hours_per_day' => 8]);
        Leave::factory()->on('2026-07-27', '2026-07-28')->create([
            'employee_id' => $employee->id,
            'hours_per_day' => 4,
        ]);

        $this->assertSame(8.0, $this->service->takenFor($employee, Carbon::parse(self::TODAY)));
    }

    public function test_two_records_covering_one_day_deduct_it_once(): void
    {
        $employee = $this->dailyEmployee();
        Leave::factory()->on('2026-07-27', '2026-07-29')->create(['employee_id' => $employee->id]);
        Leave::factory()->on('2026-07-28', '2026-07-30')->create(['employee_id' => $employee->id]);

        // Mon–Thu covered once each, not six days between them.
        $this->assertSame(4.0, $this->service->takenFor($employee, Carbon::parse(self::TODAY)));
    }

    public function test_deleted_leave_is_ignored(): void
    {
        $employee = $this->dailyEmployee();
        $leave = Leave::factory()->on('2026-07-27', '2026-07-28')->create(['employee_id' => $employee->id]);

        $leave->delete();

        $this->assertSame(0.0, $this->service->takenFor($employee, Carbon::parse(self::TODAY)));
    }

    public function test_the_remaining_balance_is_the_entitlement_less_what_was_taken(): void
    {
        $employee = $this->dailyEmployee(['weekly_active_days' => 5]);
        Leave::factory()->on('2026-07-27', '2026-07-29')->create(['employee_id' => $employee->id]);

        $balance = $this->service->balanceFor($employee, Carbon::parse(self::TODAY));

        $this->assertSame('days', $balance['unit']);
        $this->assertSame(20.0, $balance['entitlement']);
        $this->assertSame(3.0, $balance['taken']);
        $this->assertSame(17.0, $balance['remaining']);
    }

    // ── Week pay ─────────────────────────────────────────────────────────────

    public function test_a_daily_leave_day_pays_the_days_rate(): void
    {
        $employee = $this->dailyEmployee(['daily_rate' => 100]);
        Leave::factory()->on('2026-07-27', '2026-07-28')->create(['employee_id' => $employee->id]);

        [$days, $hours, $amount] = $this->service->weekPayFor($employee, self::WEEK_YEAR, self::WEEK_NUMBER);

        $this->assertSame(2.0, $days);
        $this->assertSame(0.0, $hours);
        $this->assertSame(200.0, $amount);
    }

    public function test_an_hourly_leave_day_pays_its_hours(): void
    {
        $employee = $this->hourlyEmployee(['hourly_rate' => 10, 'hours_per_day' => 8]);
        Leave::factory()->on('2026-07-27', '2026-07-28')->create([
            'employee_id' => $employee->id,
            'hours_per_day' => 8,
        ]);

        [$days, $hours, $amount] = $this->service->weekPayFor($employee, self::WEEK_YEAR, self::WEEK_NUMBER);

        $this->assertSame(0.0, $days);
        $this->assertSame(16.0, $hours);
        $this->assertSame(160.0, $amount);
    }

    public function test_a_day_already_marked_present_is_not_paid_as_leave_as_well(): void
    {
        // Attendance and leave on the same day would otherwise pay it twice.
        $employee = $this->dailyEmployee(['daily_rate' => 100]);
        Leave::factory()->on('2026-07-27', '2026-07-28')->create(['employee_id' => $employee->id]);

        DailyRateAttendance::create([
            'employee_id' => $employee->id,
            'year' => self::WEEK_YEAR,
            'week_number' => self::WEEK_NUMBER,
            'days_map' => ['mon' => 1, 'tue' => 0, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
            'present_days' => 1,
            'total_working_days' => 6,
        ]);

        [$days, , $amount] = $this->service->weekPayFor($employee, self::WEEK_YEAR, self::WEEK_NUMBER);

        $this->assertSame(1.0, $days, 'only the Tuesday is paid as leave');
        $this->assertSame(100.0, $amount);
    }

    public function test_leave_falling_after_a_leaving_date_is_not_paid(): void
    {
        // Deactivation takes effect from its own date, so Wednesday is already gone.
        $employee = $this->dailyEmployee(['daily_rate' => 100, 'deactivated_at' => '2026-07-29']);
        Leave::factory()->on('2026-07-27', '2026-07-30')->create(['employee_id' => $employee->id]);

        [$days, , $amount] = $this->service->weekPayFor($employee, self::WEEK_YEAR, self::WEEK_NUMBER);

        $this->assertSame(2.0, $days);
        $this->assertSame(200.0, $amount);
    }

    public function test_a_week_without_leave_pays_nothing(): void
    {
        $employee = $this->dailyEmployee();

        $this->assertSame([0.0, 0.0, 0.0], $this->service->weekPayFor($employee, self::WEEK_YEAR, self::WEEK_NUMBER));
    }

    public function test_leave_on_a_non_working_day_pays_nothing(): void
    {
        // Sunday is outside a five-day week, so a Sunday of leave is worth nothing.
        $employee = $this->dailyEmployee(['weekly_active_days' => 5]);
        Leave::factory()->on('2026-08-02')->create(['employee_id' => $employee->id]);

        $this->assertSame([0.0, 0.0, 0.0], $this->service->weekPayFor($employee, self::WEEK_YEAR, self::WEEK_NUMBER));
    }

    /**
     * @param  array<string, float>  $hours
     */
    private function clockHours(Employee $employee, int $year, int $week, array $hours): void
    {
        HourlyAttendance::create([
            'employee_id' => $employee->id,
            'year' => $year,
            'week_number' => $week,
            'hours_map' => array_merge(
                ['mon' => 0, 'tue' => 0, 'wed' => 0, 'thu' => 0, 'fri' => 0, 'sat' => 0, 'sun' => 0],
                $hours
            ),
            'ot_map' => [],
            'total_hours' => array_sum($hours),
            'overtime_hours' => 0,
        ]);
    }
}
