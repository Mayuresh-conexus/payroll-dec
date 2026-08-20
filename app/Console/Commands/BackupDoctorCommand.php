<?php

namespace App\Console\Commands;

use App\Services\BackupEnvironmentReport;
use Illuminate\Console\Command;

/**
 * Reports why backup/restore is slow or failing on a given server.
 *
 * The checks themselves live in BackupEnvironmentReport, because the answer
 * depends on which PHP is asking: shared hosts often give the CLI a stricter
 * php.ini than the web server, so a terminal diagnosis can describe an
 * environment the restore never runs in. The same report is on the Backups page
 * for exactly that reason.
 */
class BackupDoctorCommand extends Command
{
    protected $signature = 'backup:doctor';

    protected $description = 'Diagnose the environment that database backup and restore depend on';

    public function handle(BackupEnvironmentReport $reporter): int
    {
        $report = $reporter->run();

        $this->newLine();
        $this->line('<options=bold>Backup environment check</>');
        $this->line('  <fg=gray>'.$report['sapi'].' · '.$report['ini'].'</>');
        $this->newLine();

        foreach ($report['checks'] as $check) {
            $this->line(sprintf(
                '  %s %-24s %s%s',
                match ($check['status']) {
                    BackupEnvironmentReport::PASS => '<info>✓</info>',
                    BackupEnvironmentReport::FAIL => '<fg=red>✗</>',
                    default => '<fg=yellow>–</>',
                },
                $check['label'],
                $check['detail'],
                $check['elapsed'] !== null ? ' <fg=gray>('.$this->ms($check['elapsed']).')</>' : ''
            ));
        }

        $this->newLine();

        if ($report['ok']) {
            $this->info('All checks passed.');

            return self::SUCCESS;
        }

        $this->error('One or more checks failed — backup/restore will not work until these are fixed.');
        $this->warnAboutSapiDifference($report);

        return self::FAILURE;
    }

    /**
     * The CLI and the web server load different php.ini files, and only the web
     * one governs a restore started from the browser. Saying so stops a CLI-only
     * result being read as the explanation for a stalled restore in the UI.
     *
     * @param  array{sapi: string, disable_functions: string}  $report
     */
    private function warnAboutSapiDifference(array $report): void
    {
        $this->newLine();
        $this->line('  <comment>This is the '.$report['sapi'].' configuration. The web server loads its own php.ini,</comment>');
        $this->line('  <comment>so a restore started from the browser may not be affected by this at all.</comment>');
        $this->line('  <comment>Open Backups → Diagnostics in the app to run these same checks there.</comment>');
        $this->newLine();
        $this->line('  Current disable_functions:');
        $this->line('    <fg=gray>'.($report['disable_functions'] ?: '(empty)').'</>');
    }

    private function ms(float $seconds): string
    {
        return $seconds >= 1
            ? round($seconds, 1).'s'
            : round($seconds * 1000).'ms';
    }
}
