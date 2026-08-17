<?php

use App\Models\DailyRateAttendance;
use App\Models\Employee;
use App\Models\PayrollItem;
use App\Models\PayrollRun;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function adminUser(): User
{
    return User::factory()->create(['role' => 'admin']);
}

function fullWeekDaysMap(): array
{
    return [
        'mon' => 1,
        'tue' => 1,
        'wed' => 1,
        'thu' => 1,
        'fri' => 1,
        'sat' => 1,
        'sun' => 1,
    ];
}

test('monthly advance does not double count overlapping week advances', function () {
    $this->actingAs(adminUser());

    $employee = Employee::factory()->create([
        'type' => 'daily_rate',
        'daily_rate' => 100,
        'bank_transfer_fix_amount' => 2000,
    ]);

    $week18Run = PayrollRun::create([
        'period_type' => 'weekly',
        'year' => 2026,
        'week_number' => 18,
        'status' => 'draft',
    ]);

    PayrollItem::create([
        'payroll_run_id' => $week18Run->id,
        'employee_id' => $employee->id,
        'type' => 'daily_rate',
        'weekly_amount' => 600,
        'gross_amount' => 600,
        'cash_amount' => 0,
        'bank_amount' => 2000,
        'advance_given' => 1400,
        'advance_recovered' => 0,
        'advance_balance' => 1400,
    ]);

    DailyRateAttendance::create([
        'employee_id' => $employee->id,
        'year' => 2026,
        'week_number' => 18,
        'total_working_days' => 6,
        'present_days' => 6,
        'locked' => true,
        'days_map' => fullWeekDaysMap(),
        'overtime_map' => [],
        'overtime_amount' => 0,
    ]);

    $week19Run = PayrollRun::create([
        'period_type' => 'weekly',
        'year' => 2026,
        'week_number' => 19,
        'status' => 'draft',
    ]);

    PayrollItem::create([
        'payroll_run_id' => $week19Run->id,
        'employee_id' => $employee->id,
        'type' => 'daily_rate',
        'weekly_amount' => 2000,
        'gross_amount' => 2000,
        'cash_amount' => 0,
        'bank_amount' => 2000,
        'advance_given' => 0,
        'advance_recovered' => 0,
        'advance_balance' => 1400,
    ]);

    DailyRateAttendance::create([
        'employee_id' => $employee->id,
        'year' => 2026,
        'week_number' => 19,
        'total_working_days' => 6,
        'present_days' => 6,
        'locked' => true,
        'days_map' => fullWeekDaysMap(),
        'overtime_map' => [],
        'overtime_amount' => 0,
    ]);

    $response = $this->get('/payroll/monthly?month=2026-05');
    $response->assertOk();

    $rows = $response->viewData('rows');
    $row = $rows->first(fn ($r) => $r['employee']->id === $employee->id);

    expect($row)->not->toBeNull();
    expect((float) $row['advance_before_settle'])->toBe(1400.0);
});

test('monthly view includes overlapping week from prior iso year', function () {
    $this->actingAs(adminUser());

    $employee = Employee::factory()->create([
        'type' => 'daily_rate',
        'daily_rate' => 100,
        'bank_transfer_fix_amount' => 700,
    ]);

    $week53Run = PayrollRun::create([
        'period_type' => 'weekly',
        'year' => 2020,
        'week_number' => 53,
        'status' => 'draft',
    ]);

    PayrollItem::create([
        'payroll_run_id' => $week53Run->id,
        'employee_id' => $employee->id,
        'type' => 'daily_rate',
        'weekly_amount' => 700,
        'gross_amount' => 700,
        'cash_amount' => 0,
        'bank_amount' => 700,
        'advance_given' => 0,
        'advance_recovered' => 0,
        'advance_balance' => 0,
    ]);

    DailyRateAttendance::create([
        'employee_id' => $employee->id,
        'year' => 2020,
        'week_number' => 53,
        'total_working_days' => 6,
        'present_days' => 6,
        'locked' => true,
        'days_map' => fullWeekDaysMap(),
        'overtime_map' => [],
        'overtime_amount' => 0,
    ]);

    $response = $this->get('/payroll/monthly?month=2021-01');
    $response->assertOk();

    $rows = $response->viewData('rows');
    $row = $rows->first(fn ($r) => $r['employee']->id === $employee->id);

    expect($row)->not->toBeNull();
    expect((float) $row['bank_fix_total'])->toBeGreaterThan(0);
});

use App\Models\HourlyAttendance;

test('monthly settlement max bank uses actual bank amount for hourly employees', function () {
    $this->actingAs(adminUser());

    $employee = Employee::factory()->create([
        'type' => 'hourly',
        'hourly_rate' => 10,
        'bank_transfer_fix_amount' => null, // Hourly employees don't use this
    ]);

    $run = PayrollRun::create([
        'period_type' => 'weekly',
        'year' => 2026,
        'week_number' => 18,
        'status' => 'draft',
    ]);

    // Hourly employee has variable weekly bank transfer based on hours
    PayrollItem::create([
        'payroll_run_id' => $run->id,
        'employee_id' => $employee->id,
        'type' => 'hourly',
        'weekly_amount' => 400,
        'gross_amount' => 400,
        'cash_amount' => 100,
        'bank_amount' => 300, // <--- This is what should be used!
        'advance_given' => 0,
        'advance_recovered' => 0,
        'advance_balance' => 0,
    ]);

    HourlyAttendance::create([
        'employee_id' => $employee->id,
        'year' => 2026,
        'week_number' => 18,
        'locked' => true,
        'hours_map' => ['mon' => 8, 'tue' => 8, 'wed' => 8, 'thu' => 8, 'fri' => 8],
        'ot_map' => [],
    ]);

    $response = $this->get('/payroll/monthly?month=2026-05');
    $response->assertOk();

    $rows = $response->viewData('rows');
    $row = $rows->first(fn ($r) => $r['employee']->id === $employee->id);

    expect($row)->not->toBeNull();
    // The $factor prorates the amount for the overlapping week.
    // Week 18 2026 has 1 working day in May (May 1 is a Friday) out of 5 (Mon-Fri) for hourly.
    // 1 / 5 = 0.2 factor. 300 * 0.2 = 60.0.
    expect((float) $row['bank_fix_total'])->toBe(60.0);

});

it('settles a bank holiday whole into its own month only', function () {
    // Week 31 of 2026 spans 27 Jul - 2 Aug, so it belongs to both months. It
    // settles July, and the premium must land there whole — not prorated by the
    // days-in-month factor, and not handed to August as well.
    App\Models\Holiday::factory()->on('2026-07-08')->create();

    $emp = Employee::factory()->create([
        'type' => 'daily_rate', 'daily_rate' => 135, 'bh_bank_percent' => 40,
    ]);

    $service = app(App\Services\AttendanceService::class);
    $service->saveDailyEmployee($emp->id, ['days' => ['wed' => 1]], 2026, 28, false);
    $service->saveDailyEmployee($emp->id, ['days' => ['mon' => 1, 'tue' => 1]], 2026, 31, false);

    $controller = app(App\Http\Controllers\MonthlyPayrollController::class);
    $build = new ReflectionMethod($controller, 'buildMonthRows');
    $build->setAccessible(true);

    $july = collect($build->invoke($controller, '2026-07'))->firstWhere('employee.id', $emp->id);
    $august = collect($build->invoke($controller, '2026-08'))->firstWhere('employee.id', $emp->id);

    expect((float) $july['bh_amount'])->toBe(135.0)          // whole premium, unprorated
        ->and((float) $july['bh_cash'])->toBe(81.0)
        ->and((float) $july['bh_bank'])->toBe(54.0)
        ->and((float) ($august['bh_amount'] ?? 0))->toBe(0.0); // August must not claim it too
});
