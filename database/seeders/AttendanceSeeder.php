<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use Carbon\Carbon;

/**
 * Seeds 12 weeks of attendance: weeks 49-52 of 2025 + weeks 1-8 of 2026.
 * This covers December 2025, January 2026, and February 2026,
 * including boundary weeks that straddle two months:
 *   Week 1/2026  = Dec 29 Mon – Jan 4 Sun   (straddles Dec→Jan)
 *   Week 5/2026  = Jan 26 Mon – Feb 1 Sun   (straddles Jan→Feb)
 *   Week 9/2026  = Feb 23 Mon – Mar 1 Sun   (straddles Feb→Mar)
 */
class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        // Weeks to seed: 2025-W49 through 2025-W52, then 2026-W01 through 2026-W08
        $weeks = [];
        for ($w = 49; $w <= 52; $w++) {
            $weeks[] = ['year' => 2025, 'week' => $w];
        }
        for ($w = 1; $w <= 8; $w++) {
            $weeks[] = ['year' => 2026, 'week' => $w];
        }

        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        $dailyEmployees = Employee::where('type', 'daily_rate')->where('is_active', true)->get();
        $hourlyEmployees = Employee::where('type', 'hourly')->where('is_active', true)->get();

        // Use a deterministic seed so results are reproducible
        mt_srand(42);

        foreach ($weeks as $wk) {
            $year = $wk['year'];
            $week = $wk['week'];

            // Current week (week of today) stays unlocked; all past weeks are locked
            $isCurrentWeek = ($year == now()->year && $week == now()->weekOfYear);

            // ── Daily rate employees ──────────────────────────
            foreach ($dailyEmployees as $emp) {
                // Randomise 4-6 present days per week
                $presentDays = mt_rand(4, 6);
                $daysMap = [];
                $presentCount = 0;

                // Randomly distribute present days across Mon–Sat (Sun usually off)
                $availableDays = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
                shuffle($availableDays);
                $selectedDays = array_slice($availableDays, 0, $presentDays);

                foreach ($dayKeys as $d) {
                    $daysMap[$d] = in_array($d, $selectedDays) ? 1 : 0;
                    if ($daysMap[$d])
                        $presentCount++;
                }

                // Overtime: ~30% chance of OT on any given week, ₹200-800
                $otAmount = (mt_rand(1, 10) <= 3) ? mt_rand(2, 8) * 100 : 0;

                $otMap = [];
                if ($otAmount > 0) {
                    // Put OT on a random present day
                    $presentDayKeys = array_keys(array_filter($daysMap));
                    if (!empty($presentDayKeys)) {
                        $otDay = $presentDayKeys[array_rand($presentDayKeys)];
                        foreach ($dayKeys as $d) {
                            $otMap[$d] = ($d === $otDay) ? $otAmount : 0;
                        }
                    }
                }

                DailyRateAttendance::updateOrCreate(
                ['employee_id' => $emp->id, 'year' => $year, 'week_number' => $week],
                [
                    'present_days' => $presentCount,
                    'total_working_days' => 6,
                    'days_map' => $daysMap,
                    'overtime_map' => !empty($otMap) ? $otMap : null,
                    'overtime_amount' => $otAmount,
                    'locked' => !$isCurrentWeek,
                ]
                );
            }

            // ── Hourly employees ─────────────────────────────
            foreach ($hourlyEmployees as $emp) {
                $hpd = $emp->hours_per_day ?? 8;
                $hoursMap = [];
                $otMap = [];
                $totalHours = 0;
                $totalOtHours = 0;

                // Mon-Sat: work with some variation, Sun: ~20% chance
                foreach ($dayKeys as $d) {
                    if ($d === 'sun') {
                        $present = mt_rand(1, 5) === 1; // 20% chance
                    }
                    else {
                        $present = mt_rand(1, 10) >= 2; // 90% chance
                    }

                    if ($present) {
                        // Regular hours: slightly variable (±1hr)
                        $hrs = $hpd + (mt_rand(-2, 2) * 0.5);
                        $hrs = max(4, min($hpd + 2, $hrs));
                        $hoursMap[$d] = round($hrs, 1);
                        $totalHours += $hoursMap[$d];

                        // OT hours: ~25% chance per day, 1-3 hours
                        if (mt_rand(1, 4) === 1) {
                            $ot = mt_rand(1, 3);
                            $otMap[$d] = $ot;
                            $totalOtHours += $ot;
                        }
                        else {
                            $otMap[$d] = 0;
                        }
                    }
                    else {
                        $hoursMap[$d] = 0;
                        $otMap[$d] = 0;
                    }
                }

                HourlyAttendance::updateOrCreate(
                ['employee_id' => $emp->id, 'year' => $year, 'week_number' => $week],
                [
                    'total_hours' => round($totalHours, 1),
                    'overtime_hours' => $totalOtHours,
                    'hours_map' => $hoursMap,
                    'ot_map' => $otMap,
                    'locked' => !$isCurrentWeek,
                ]
                );
            }
        }

        mt_srand(); // Reset random seed
    }
}
