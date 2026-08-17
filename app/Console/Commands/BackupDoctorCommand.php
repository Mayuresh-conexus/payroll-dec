<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Reports why backup/restore is slow or failing on a given server.
 *
 * Backup and restore shell out to the mysql CLI, so they depend on things that
 * are invisible from the application side: whether proc_open is permitted,
 * whether the client binaries exist, and how long the database takes to answer.
 * Each check is individually timed so a stall points at its own cause.
 */
class BackupDoctorCommand extends Command
{
    protected $signature = 'backup:doctor';

    protected $description = 'Diagnose the environment that database backup and restore depend on';

    public function handle(): int
    {
        $this->newLine();
        $this->line('<options=bold>Backup environment check</>');
        $this->newLine();

        $ok = $this->checkProcessFunctions();
        $ok = $this->checkBinary('mysql', config('backup.mysql_path')) && $ok;
        $ok = $this->checkBinary('mysqldump', config('backup.mysqldump_path')) && $ok;
        $ok = $this->checkConnection() && $ok;

        $this->reportDatabaseSize();
        $this->reportLimits();

        $this->newLine();

        if (! $ok) {
            $this->error('One or more checks failed — backup/restore will not work until these are fixed.');

            return self::FAILURE;
        }

        $this->info('All checks passed.');

        return self::SUCCESS;
    }

    private function checkProcessFunctions(): bool
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $missing = [];

        foreach (['proc_open', 'proc_close'] as $function) {
            if (! function_exists($function) || in_array($function, $disabled, true)) {
                $missing[] = $function;
            }
        }

        if ($missing !== []) {
            $this->result('PHP process functions', false, implode(', ', $missing).' disabled in php.ini');

            return false;
        }

        $this->result('PHP process functions', true, 'proc_open available');

        return true;
    }

    private function checkBinary(string $label, string $path): bool
    {
        [$elapsed, $process] = $this->timed(fn (): Process => $this->runProcess([$path, '--version'], 15));

        if (! $process->isSuccessful()) {
            $this->result($label, false, $process->getExitCode() === 127
                ? "not found at \"{$path}\" — set ".strtoupper($label).'_PATH in .env'
                : trim($process->getErrorOutput()));

            return false;
        }

        $this->result($label, true, trim($process->getOutput()), $elapsed);

        return true;
    }

    private function checkConnection(): bool
    {
        $connectTimeout = (int) config('backup.connect_timeout', 10);
        $db = config('database.connections.mysql');
        $target = ! empty($db['unix_socket']) ? $db['unix_socket'] : $db['host'].':'.$db['port'];

        [$elapsed, $process] = $this->timed(fn (): Process => $this->runProcess([
            config('backup.mysql_path'),
            '--defaults-extra-file='.$this->optionFile(),
            '--batch',
            '--skip-column-names',
            '--execute=SELECT 1',
            $db['database'],
        ], $connectTimeout + 5));

        if (! $process->isSuccessful()) {
            $this->result('Database connection', false, trim($process->getErrorOutput()).' (via '.$target.')');

            return false;
        }

        $this->result('Database connection', true, 'reachable via '.$target, $elapsed);

        if ($elapsed > 1.0) {
            $this->line('    <comment>Connecting took '.$this->ms($elapsed).' — unusually slow; this is what makes restores drag.</comment>');
        }

        return true;
    }

    private function reportDatabaseSize(): void
    {
        $db = config('database.connections.mysql');

        [$elapsed, $process] = $this->timed(fn (): Process => $this->runProcess([
            config('backup.mysql_path'),
            '--defaults-extra-file='.$this->optionFile(),
            '--batch',
            '--skip-column-names',
            '--execute=SELECT ROUND(SUM(data_length + index_length) / 1024, 1), COUNT(*) '
                ."FROM information_schema.tables WHERE table_schema = '{$db['database']}'",
            $db['database'],
        ], 30));

        if (! $process->isSuccessful()) {
            return;
        }

        [$kb, $tables] = array_pad(preg_split('/\s+/', trim($process->getOutput())), 2, '?');

        $this->result('Database size', true, "{$kb} KB across {$tables} tables", $elapsed);
    }

    private function reportLimits(): void
    {
        $this->result('Process timeout', true, config('backup.process_timeout').'s per mysql/mysqldump call');
        $this->result('Connect timeout', true, config('backup.connect_timeout').'s before giving up on the DB host');
        $this->result('PHP max_execution_time', true, (ini_get('max_execution_time') ?: '0').'s (0 = unlimited)');
    }

    /**
     * @param  list<string>  $command
     */
    private function runProcess(array $command, int $timeout): Process
    {
        $process = new Process($command);
        $process->setTimeout($timeout);

        try {
            $process->run();
        } catch (\Throwable $e) {
            // A timeout or a disabled proc_open still needs to produce a Process
            // object for the caller to inspect, so swallow and let isSuccessful() speak.
        }

        return $process;
    }

    /**
     * @return array{0: float, 1: Process}
     */
    private function timed(callable $callback): array
    {
        $start = microtime(true);
        $process = $callback();

        return [microtime(true) - $start, $process];
    }

    private function optionFile(): string
    {
        static $path = null;

        if ($path !== null) {
            return $path;
        }

        $db = config('database.connections.mysql');
        $lines = ['[client]'];

        if (! empty($db['unix_socket'])) {
            $lines[] = 'socket='.$db['unix_socket'];
        } else {
            $lines[] = 'host='.$db['host'];
            $lines[] = 'port='.$db['port'];
        }

        $lines[] = 'user='.$db['username'];
        $lines[] = 'password='.$db['password'];
        $lines[] = '';
        $lines[] = '[mysql]';
        $lines[] = 'connect-timeout='.(int) config('backup.connect_timeout', 10);

        $path = tempnam(sys_get_temp_dir(), 'mysqlopt_');
        file_put_contents($path, implode("\n", $lines).PHP_EOL);
        chmod($path, 0600);

        register_shutdown_function(static fn () => @unlink($path));

        return $path;
    }

    private function result(string $label, bool $passed, string $detail, ?float $elapsed = null): void
    {
        $this->line(sprintf(
            '  %s %-24s %s%s',
            $passed ? '<info>✓</info>' : '<fg=red>✗</>',
            $label,
            $detail,
            $elapsed !== null ? ' <fg=gray>('.$this->ms($elapsed).')</>' : ''
        ));
    }

    private function ms(float $seconds): string
    {
        return $seconds >= 1
            ? round($seconds, 1).'s'
            : round($seconds * 1000).'ms';
    }
}
