<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\DailyRateAttendance;
use App\Models\HourlyAttendance;
use Carbon\Carbon;
use Illuminate\Console\Command;

/**
 * Who changed what on this installation, and when.
 *
 * Written for a team split across two countries: the office using the system
 * and the developers testing it both leave marks in the same database, and
 * "did the client actually do this?" is otherwise guesswork. Times are shown in
 * both places at once, and each person's addresses are listed, because the
 * address is what actually separates the office from a developer.
 *
 * Read-only — it runs no writes, so it is safe against live data.
 */
class ActivityReportCommand extends Command
{
    protected $signature = 'activity:report
        {--days=7 : How far back to look}
        {--limit=40 : How many individual changes to list}
        {--here=Asia/Kolkata : Your own timezone}
        {--there=Europe/Dublin : The office timezone}';

    protected $description = 'Report who changed what, and when, across both timezones';

    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));
        $since = now()->subDays($days);
        $here = (string) $this->option('here');
        $there = (string) $this->option('there');

        $this->newLine();
        $this->line('<options=bold>Activity in the last '.$days.' day'.($days === 1 ? '' : 's').'</>');
        $this->line('  <fg=gray>since '.$this->when($since, $there, $here).'</>');
        $this->line('  <fg=gray>times shown as '.$there.' / '.$here.'</>');

        $this->reportByUser($since);
        $this->reportAttendance($since, $there, $here);
        $this->reportChanges($since, $there, $here);

        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Who has been active, and from where.
     */
    private function reportByUser(Carbon $since): void
    {
        $logs = AuditLog::with('user')->where('created_at', '>=', $since)->get();

        $this->section('Who made changes');

        if ($logs->isEmpty()) {
            $this->line('  <fg=gray>nobody — no recorded changes in this window</>');

            return;
        }

        foreach ($logs->groupBy('user_id') as $entries) {
            $user = $entries->first()->user;
            $addresses = $entries->pluck('ip_address')->filter()->unique()->values();

            $this->line(sprintf(
                '  %-28s %3d change%s',
                $user?->name ?? 'system / not signed in',
                $entries->count(),
                $entries->count() === 1 ? ' ' : 's'
            ));

            // The address is the thing that tells the office apart from a
            // developer, so it is spelled out rather than summarised.
            foreach ($addresses as $address) {
                $seen = $entries->where('ip_address', $address);
                $this->line(sprintf('      <fg=gray>from %-16s %d change%s</>',
                    $address, $seen->count(), $seen->count() === 1 ? '' : 's'));
            }
        }
    }

    /**
     * Attendance is not attributed to a user before this release, so it is
     * reported separately rather than being quietly left out.
     */
    private function reportAttendance(Carbon $since, string $there, string $here): void
    {
        $this->section('Attendance touched');

        $weeks = [];

        foreach ([DailyRateAttendance::class, HourlyAttendance::class] as $model) {
            foreach ($model::where('updated_at', '>=', $since)->get() as $row) {
                $key = $row->year.'-'.str_pad((string) $row->week_number, 2, '0', STR_PAD_LEFT);
                $weeks[$key]['employees'] = ($weeks[$key]['employees'] ?? 0) + 1;
                $weeks[$key]['last'] = max($weeks[$key]['last'] ?? $row->updated_at, $row->updated_at);
            }
        }

        if ($weeks === []) {
            $this->line('  <fg=gray>none</>');

            return;
        }

        krsort($weeks);

        foreach ($weeks as $key => $week) {
            [$year, $number] = explode('-', $key);
            $this->line(sprintf(
                '  week %-2s of %s  %2d employee row%s  last change %s',
                ltrim($number, '0'),
                $year,
                $week['employees'],
                $week['employees'] === 1 ? ' ' : 's',
                $this->when($week['last'], $there, $here)
            ));
        }
    }

    private function reportChanges(Carbon $since, string $there, string $here): void
    {
        $limit = max(1, (int) $this->option('limit'));

        $logs = AuditLog::with('user')
            ->where('created_at', '>=', $since)
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $this->section('Recent changes');

        if ($logs->isEmpty()) {
            $this->line('  <fg=gray>none</>');

            return;
        }

        foreach ($logs as $log) {
            $this->line(sprintf(
                '  %-26s %-14s %-8s %s%s',
                $this->when($log->created_at, $there, $here),
                mb_substr($log->user?->name ?? 'system', 0, 14),
                $log->action,
                $log->model_type,
                $log->model_id ? ' #'.$log->model_id : ''
            ));
        }

        $total = AuditLog::where('created_at', '>=', $since)->count();

        if ($total > $logs->count()) {
            $this->line('  <fg=gray>… '.($total - $logs->count()).' older change'
                .($total - $logs->count() === 1 ? '' : 's').' not shown; raise --limit to see them</>');
        }
    }

    /**
     * One moment, in both places. Stored times are UTC, and neither reader
     * thinks in UTC.
     */
    private function when(Carbon|string $at, string $there, string $here): string
    {
        $at = $at instanceof Carbon ? $at : Carbon::parse($at);

        return $at->copy()->timezone($there)->format('D d M H:i')
            .' / '.$at->copy()->timezone($here)->format('H:i');
    }

    private function section(string $title): void
    {
        $this->newLine();
        $this->line('  <options=bold>'.$title.'</>');
    }
}
