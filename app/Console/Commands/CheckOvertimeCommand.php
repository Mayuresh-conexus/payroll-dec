<?php

namespace App\Console\Commands;

use App\Models\HourlyAttendance;
use App\Models\PayrollRun;
use Illuminate\Console\Command;

/**
 * Read-only audit for overtime that drifted away from the logged hours.
 *
 * Overtime is the part of a day above the employee's daily norm, so it is a
 * function of hours_map and hours_per_day. Weeks saved before that became the
 * rule can hold a larger figure, which inflates overtime pay. This only reports;
 * healing a week is done by refreshing it on the payroll page.
 */
class CheckOvertimeCommand extends Command
{
    protected $signature = 'attendance:check-overtime {--year= : Limit to a single year}';

    protected $description = 'Report hourly weeks whose stored overtime does not match the logged hours';

    public function handle(): int
    {
        $query = HourlyAttendance::with('employee')->orderBy('year')->orderBy('week_number');

        if ($this->option('year')) {
            $query->where('year', (int) $this->option('year'));
        }

        $rows = [];
        $checked = 0;

        foreach ($query->get() as $attendance) {
            $hoursPerDay = (float) ($attendance->employee->hours_per_day ?? 0);

            if ($hoursPerDay <= 0) {
                continue;
            }

            $checked++;
            $storedOt = 0.0;
            $correctOt = 0.0;

            foreach (['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'] as $day) {
                $hours = (float) ($attendance->hours_map[$day] ?? 0);
                $storedOt += (float) ($attendance->ot_map[$day] ?? 0);
                $correctOt += $hours > $hoursPerDay ? $hours - $hoursPerDay : 0.0;
            }

            if (abs($storedOt - $correctOt) < 0.001) {
                continue;
            }

            $rows[] = [
                $attendance->employee->employee_code ?? '—',
                $attendance->employee->name ?? "#{$attendance->employee_id}",
                $attendance->year,
                $attendance->week_number,
                rtrim(rtrim(number_format($storedOt, 2), '0'), '.'),
                rtrim(rtrim(number_format($correctOt, 2), '0'), '.'),
                $this->weekState($attendance->year, $attendance->week_number),
            ];
        }

        if ($rows === []) {
            $this->info("Overtime matches the logged hours in all {$checked} hourly weeks.");

            return self::SUCCESS;
        }

        $this->newLine();
        $this->table(['Code', 'Employee', 'Year', 'Week', 'Stored OT', 'Should be', 'Payroll run'], $rows);

        $this->warn(count($rows).' of '.$checked.' hourly weeks have overtime above the logged hours.');
        $this->line('Draft weeks are corrected by opening that week on the payroll page and using Refresh.');
        $this->line('Note that Refresh also resets cash/bank splits back to employee defaults.');
        $this->line('Finalized weeks are left alone — reopening a paid week is a business decision.');

        return self::SUCCESS;
    }

    private function weekState(int $year, int $week): string
    {
        $run = PayrollRun::where('year', $year)
            ->where('week_number', $week)
            ->where('period_type', 'weekly')
            ->first();

        if (! $run) {
            return 'none';
        }

        // 'final' is the status refreshWeek refuses to touch.
        return $run->status === 'final' ? '<fg=red>finalized</>' : $run->status;
    }
}
