<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\EmployeeRate;
use App\Models\Holiday;
use App\Models\Leave;
use App\Models\User;
use App\Services\AttendanceService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

/**
 * A worked example of the kind of payroll this app is actually run against:
 * a small Irish warehouse and production operation, paid weekly in euro.
 *
 * Everything is deterministic — the same command twice produces the same books,
 * and rows are matched on their codes rather than appended — so it can be re-run
 * while demonstrating without piling up duplicates.
 *
 * Attendance is fed through AttendanceService rather than written straight to the
 * table, so the demo exercises the real calculation and arrives with payroll,
 * bank-holiday settlements and paid leave already worked out.
 */
class DemoDataSeeder extends Seeder
{
    /**
     * How many completed weeks of history to lay down.
     *
     * Sixteen always spans at least three whole calendar months, and the Irish
     * calendar never leaves three consecutive months without a bank holiday — so
     * whenever this is run there is a settled month in the window and the payroll
     * table has BH columns worth looking at.
     */
    public const WEEKS_OF_HISTORY = 16;

    /** Codes owned by this seeder, so a re-run updates rather than duplicates. */
    private const EMPLOYEES = [
        // code, name, dept, type, rate, hours/day, days/week, bank fix, BH bank %
        ['WWF01', 'Anton Leahu', 'Warehouse', 'daily_rate', 135.00, null, 6, 500.00, 63.7],
        ['WWF02', 'Billal Maroufi', 'Warehouse', 'daily_rate', 150.00, null, 6, 450.00, 50.0],
        ['WWF03', 'Derek Gallagher', 'Warehouse', 'hourly', 17.50, 8.0, 5, 700.00, 0.0],
        ['WWF04', 'Lukasz Nowak', 'Warehouse', 'daily_rate', 130.00, null, 6, 400.00, 40.0],
        ['PRD01', 'Marek Kowalski', 'Production', 'daily_rate', 145.00, null, 6, 480.00, 40.0],
        ['PRD02', 'Aoife Byrne', 'Production', 'hourly', 16.00, 8.0, 5, 520.00, 0.0],
        ['QUA01', 'Priya Nair', 'Quality', 'hourly', 19.25, 7.5, 5, 600.00, 25.0],
        ['ADM01', 'Siobhan Doyle', 'Admin', 'daily_rate', 160.00, null, 5, 650.00, 100.0],
        ['MNT01', 'Tomasz Wojcik', 'Maintenance', 'hourly', 21.00, 8.0, 5, 750.00, 0.0],
        ['TRN01', 'Ionut Popescu', 'Transport', 'daily_rate', 140.00, null, 6, 500.00, 60.0],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->warn('DemoDataSeeder refused to run: this looks like production.');

            return;
        }

        $admin = $this->seedAdmin();
        $employees = $this->seedEmployees();
        $this->seedHolidays();
        $this->seedRateHistory($employees);
        $this->seedLeave($employees);
        $this->seedManager($employees, $admin);
        $this->seedAttendance($employees);

        $this->command?->info('Demo payroll seeded: '.count($employees).' employees, '
            .self::WEEKS_OF_HISTORY.' weeks of attendance, Irish bank holidays for '.now()->year.'.');
    }

    /**
     * Somebody has to own the demo, and the assignment pivot needs a real user to
     * credit, so the seeder stands on its own rather than assuming a prior run.
     */
    private function seedAdmin(): User
    {
        return User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Admin', 'role' => 'admin', 'password' => bcrypt('password')]
        );
    }

    /**
     * @return array<string, Employee>
     */
    private function seedEmployees(): array
    {
        $employees = [];

        foreach (self::EMPLOYEES as $index => [$code, $name, $dept, $type, $rate, $hours, $days, $bankFix, $bhPercent]) {
            $employees[$code] = Employee::updateOrCreate(
                ['employee_code' => $code],
                [
                    'name' => $name,
                    'department' => $dept,
                    'type' => $type,
                    // Staggered start dates give every employee their own leave
                    // year, which is what makes the balances page worth looking at.
                    'joining_date' => now()->startOfDay()->subMonths(7 + ($index * 5)),
                    'daily_rate' => $type === 'daily_rate' ? $rate : null,
                    'hourly_rate' => $type === 'hourly' ? $rate : null,
                    'hours_per_day' => $hours,
                    'weekly_active_days' => $days,
                    'bank_transfer_fix_amount' => $bankFix,
                    'bh_bank_percent' => $bhPercent,
                    'bank_name' => 'Bank of Ireland',
                    'bank_account' => 'IE29 BOFI 9000 01'.str_pad((string) (23456 + $index), 5, '0', STR_PAD_LEFT),
                    'is_active' => true,
                    'deactivated_at' => null,
                ]
            );
        }

        // One leaver, so the deactivation handling has something to show: still
        // listed for the weeks actually worked, paid nothing from the cutoff on.
        $employees['WWF04']->update([
            'is_active' => false,
            'deactivated_at' => $this->mondayOfWeeksAgo(2),
        ]);

        return $employees;
    }

    /**
     * The Irish bank holiday calendar for the current year.
     *
     * Entered on the dates they are actually observed, which is the rule the
     * Holidays page expects — nothing here shifts a weekend date automatically.
     */
    private function seedHolidays(): void
    {
        $year = now()->year;

        $holidays = [
            ["New Year's Day", Carbon::create($year, 1, 1)],
            ["St Brigid's Day", Carbon::parse("first monday of february {$year}")],
            ["St Patrick's Day", Carbon::create($year, 3, 17)],
            ['Easter Monday', Carbon::parse('@'.easter_date($year))->addDay()],
            ['May Bank Holiday', Carbon::parse("first monday of may {$year}")],
            ['June Bank Holiday', Carbon::parse("first monday of june {$year}")],
            ['August Bank Holiday', Carbon::parse("first monday of august {$year}")],
            ['October Bank Holiday', Carbon::parse("last monday of october {$year}")],
            ['Christmas Day', Carbon::create($year, 12, 25)],
            ["St Stephen's Day", Carbon::create($year, 12, 26)],
        ];

        foreach ($holidays as [$name, $date]) {
            // Matched with whereDate rather than updateOrCreate: these columns are
            // date casts, and a plain equality check compares 'Y-m-d' against a
            // stored midnight timestamp and never matches — which would quietly
            // seed a second copy of the calendar on every re-run.
            $holiday = Holiday::where('name', $name)->whereDate('start_date', $date)->first()
                ?? new Holiday(['name' => $name, 'start_date' => $date->toDateString()]);

            $holiday->fill(['end_date' => $date->toDateString()])->save();
        }
    }

    /**
     * A couple of raises, so the rate history has something in it and payroll has
     * a reason to snapshot the rate that applied to each week.
     *
     * @param  array<string, Employee>  $employees
     */
    private function seedRateHistory(array $employees): void
    {
        $raises = [
            ['WWF01', 'daily_rate', 128.00, 135.00],
            ['PRD01', 'daily_rate', 138.00, 145.00],
            ['QUA01', 'hourly_rate', 18.00, 19.25],
        ];

        foreach ($raises as [$code, $type, $was, $now]) {
            $employee = $employees[$code];
            $changedOn = $this->mondayOfWeeksAgo(6);

            $this->upsertRate($employee, $type, $employee->joining_date, $was, $changedOn->copy()->subDay());
            $this->upsertRate($employee, $type, $changedOn, $now, null);
        }
    }

    /**
     * One rate-history entry, matched on its start date the same careful way.
     */
    private function upsertRate(Employee $employee, string $type, Carbon $from, float $amount, ?Carbon $to): void
    {
        $rate = EmployeeRate::where('employee_id', $employee->id)
            ->where('rate_type', $type)
            ->whereDate('effective_from', $from)
            ->first()
            ?? new EmployeeRate([
                'employee_id' => $employee->id,
                'rate_type' => $type,
                'effective_from' => $from->toDateString(),
            ]);

        $rate->fill([
            'amount' => $amount,
            'effective_to' => $to?->toDateString(),
        ])->save();
    }

    /**
     * @param  array<string, Employee>  $employees
     */
    private function seedLeave(array $employees): void
    {
        $leave = [
            ['ADM01', $this->mondayOfWeeksAgo(4), 4, 'Annual leave'],
            ['PRD02', $this->mondayOfWeeksAgo(3)->addDays(2), 2, 'Family'],
            ['WWF01', $this->mondayOfWeeksAgo(1), 1, 'Appointment'],
            // One still to come, so the list has an "Upcoming" row.
            ['MNT01', $this->mondayOfWeeksAgo(-3), 5, 'Annual leave'],
        ];

        foreach ($leave as [$code, $start, $days, $reason]) {
            $employee = $employees[$code];

            $record = Leave::where('employee_id', $employee->id)->whereDate('start_date', $start)->first()
                ?? new Leave(['employee_id' => $employee->id, 'start_date' => $start->toDateString()]);

            $record->fill([
                'end_date' => $start->copy()->addDays($days - 1)->toDateString(),
                'hours_per_day' => $employee->type === 'hourly' ? $employee->hours_per_day : null,
                'reason' => $reason,
            ])->save();
        }
    }

    /**
     * A supervisor who can log in and see only their own crew.
     *
     * @param  array<string, Employee>  $employees
     */
    private function seedManager(array $employees, User $admin): void
    {
        $supervisor = $employees['PRD01'];

        $user = User::updateOrCreate(
            ['email' => 'supervisor@example.com'],
            [
                'name' => $supervisor->name,
                'role' => 'manager',
                'employee_id' => $supervisor->id,
                'password' => bcrypt('password'),
            ]
        );

        $crew = collect(['WWF01', 'WWF02', 'WWF03', 'PRD02'])
            ->map(fn (string $code): int => $employees[$code]->id);

        $user->assignedEmployees()->syncWithPivotValues($crew, [
            'assigned_by' => $admin->id,
            'assigned_at' => now(),
        ]);
    }

    /**
     * Lay down weeks of attendance and let the payroll fall out of it.
     *
     * The patterns are worked out from the employee and week rather than drawn at
     * random, so the same books come back every time: mostly full weeks, the odd
     * day off, a Saturday here and there, and overtime on the people who
     * habitually do it.
     *
     * @param  array<string, Employee>  $employees
     */
    private function seedAttendance(array $employees): void
    {
        $service = app(AttendanceService::class);
        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        // Somebody on leave is not at work, so those days are left unmarked. Marking
        // them present would be the contradiction the payroll guards against, and
        // the leave would then pay nothing — which is not what a real week looks like.
        $onLeave = $this->leaveDatesByEmployee();

        foreach (range(self::WEEKS_OF_HISTORY, 1) as $weeksAgo) {
            $monday = $this->mondayOfWeeksAgo($weeksAgo);
            $year = $monday->isoWeekYear;
            $week = $monday->isoWeek;

            foreach (array_values($employees) as $index => $employee) {
                $seed = $index + $weeksAgo;
                $workingDays = $employee->workingDaysPerWeek();

                if ($employee->type === 'daily_rate') {
                    $days = [];
                    $overtime = [];

                    foreach ($dayKeys as $offset => $key) {
                        $isWorkingDay = $offset < $workingDays;
                        // Roughly one absence every seventh employee-week.
                        $absent = $isWorkingDay && ($seed % 7 === $offset);
                        $away = isset($onLeave[$employee->id][$monday->copy()->addDays($offset)->toDateString()]);
                        $days[$key] = $isWorkingDay && ! $absent && ! $away ? 1 : 0;
                        // Overtime is paid as an amount for daily staff.
                        $overtime[$key] = ($days[$key] && $seed % 4 === 0 && $offset === 5) ? 45.00 : 0;
                    }

                    $service->saveDailyEmployee($employee->id, [
                        'days' => $days,
                        'overtime_map' => $overtime,
                    ], $year, $week, false);

                    continue;
                }

                $standard = (float) ($employee->hours_per_day ?? 8);
                $hours = [];

                foreach ($dayKeys as $offset => $key) {
                    $away = isset($onLeave[$employee->id][$monday->copy()->addDays($offset)->toDateString()]);

                    if ($offset >= $workingDays || $away) {
                        $hours[$key] = 0;

                        continue;
                    }
                    // A long day twice a fortnight, a short one now and then.
                    $hours[$key] = match (true) {
                        ($seed + $offset) % 11 === 0 => $standard + 2,
                        ($seed + $offset) % 13 === 0 => $standard - 3.5,
                        default => $standard,
                    };
                }

                $service->saveHourlyEmployee($employee->id, [
                    'hours_map' => $hours,
                ], $year, $week, false);
            }
        }
    }

    /**
     * Every date covered by leave, so attendance can leave those days unmarked.
     *
     * @return array<int, array<string, true>> employee_id => [Y-m-d => true]
     */
    private function leaveDatesByEmployee(): array
    {
        $dates = [];

        foreach (Leave::all() as $leave) {
            $cursor = $leave->start_date->copy();

            while ($cursor->lte($leave->end_date)) {
                $dates[$leave->employee_id][$cursor->toDateString()] = true;
                $cursor->addDay();
            }
        }

        return $dates;
    }

    /**
     * The Monday of a week relative to this one. Negative counts forward.
     */
    private function mondayOfWeeksAgo(int $weeks): Carbon
    {
        return now()->startOfDay()->startOfWeek(Carbon::MONDAY)->subWeeks($weeks);
    }
}
