<?php

namespace App\Services;

use Symfony\Component\Process\Process;

/**
 * What backup and restore depend on, checked and reported.
 *
 * Backup shells out to the mysql client, so it rests on things invisible from
 * the application: whether proc_open is permitted, whether the binaries exist,
 * and how long the database takes to answer.
 *
 * The same report is rendered by the console command and by the Backups page.
 * That matters more than it sounds: shared hosts routinely give the CLI a
 * different, stricter php.ini than the web server, so a diagnosis run in a
 * terminal can describe an environment the actual restore never runs in. Being
 * able to run it in the SAPI that does the work is the point.
 */
class BackupEnvironmentReport
{
    public const PASS = 'pass';

    public const FAIL = 'fail';

    public const SKIP = 'skip';

    private ?string $optionFilePath = null;

    /**
     * @return array{
     *     sapi: string,
     *     ini: string,
     *     disable_functions: string,
     *     checks: list<array{label: string, status: string, detail: string, elapsed: float|null}>,
     *     ok: bool
     * }
     */
    public function run(): array
    {
        $checks = [];
        $processes = $this->checkProcessFunctions();
        $shellAvailable = $processes['status'] === self::PASS;

        $checks[] = $this->check(
            'Backup engine',
            self::PASS,
            $shellAvailable ? 'mysqldump' : 'PHP (PDO) — proc_open unavailable, running over the database connection'
        );
        $checks[] = $processes;

        if ($shellAvailable) {
            $checks[] = $this->checkBinary('mysql', config('backup.mysql_path'));
            $checks[] = $this->checkBinary('mysqldump', config('backup.mysqldump_path'));
            $checks[] = $this->checkConnection();

            if ($size = $this->databaseSize()) {
                $checks[] = $size;
            }
        } else {
            // The mysql client is unreachable here, so those checks say nothing.
            // What matters instead is whether the fallback can do the job.
            foreach (['mysql', 'mysqldump'] as $label) {
                $checks[] = $this->check($label, self::SKIP, 'not used by the PHP engine');
            }

            $checks[] = $this->checkPdoEngine();
        }

        foreach ($this->limits() as $limit) {
            $checks[] = $limit;
        }

        $this->cleanUp();

        return [
            'sapi' => PHP_SAPI,
            'ini' => php_ini_loaded_file() ?: 'no php.ini loaded',
            'disable_functions' => (string) (ini_get('disable_functions') ?: ''),
            'checks' => $checks,
            'ok' => ! collect($checks)->contains(fn (array $c): bool => $c['status'] === self::FAIL),
        ];
    }

    /**
     * @return array{label: string, status: string, detail: string, elapsed: float|null}
     */
    private function checkProcessFunctions(): array
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
        $missing = [];

        foreach (['proc_open', 'proc_close'] as $function) {
            if (! function_exists($function) || in_array($function, $disabled, true)) {
                $missing[] = $function;
            }
        }

        if ($missing === []) {
            return $this->check('PHP process functions', self::PASS, 'proc_open available');
        }

        // Not a failure any more: the PHP engine covers this. Reported so the
        // reason the slower engine is in use is on the page, not a mystery.
        return $this->check(
            'PHP process functions',
            self::SKIP,
            implode(', ', $missing).' disabled in php.ini — using the PHP engine instead'
        );
    }

    /**
     * Can the fallback actually reach the database and carry this schema?
     */
    private function checkPdoEngine(): array
    {
        $engine = app(PdoBackupEngine::class);

        [$elapsed, $failure] = $this->timedCall(function () use ($engine): void {
            $engine->preflight();
        });

        if ($failure !== null) {
            return $this->check('Database connection (PDO)', self::FAIL, $failure);
        }

        $db = config('database.connections.mysql');
        $target = ! empty($db['unix_socket']) ? $db['unix_socket'] : $db['host'].':'.$db['port'];

        return $this->check('Database connection (PDO)', self::PASS, 'reachable via '.$target, $elapsed);
    }

    /**
     * @return array{0: float, 1: string|null} elapsed, failure message
     */
    private function timedCall(callable $callback): array
    {
        $start = microtime(true);

        try {
            $callback();
            $failure = null;
        } catch (\Throwable $e) {
            $failure = $e->getMessage();
        }

        return [microtime(true) - $start, $failure];
    }

    private function checkBinary(string $label, string $path): array
    {
        [$elapsed, $process] = $this->timed(fn (): Process => $this->runProcess([$path, '--version'], 15));

        if (! $process->isSuccessful()) {
            return $this->check($label, self::FAIL, $process->getExitCode() === 127
                ? "not found at \"{$path}\" — set ".strtoupper($label).'_PATH in .env'
                : trim($process->getErrorOutput()));
        }

        return $this->check($label, self::PASS, trim($process->getOutput()), $elapsed);
    }

    private function checkConnection(): array
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
            return $this->check('Database connection', self::FAIL, trim($process->getErrorOutput()).' (via '.$target.')');
        }

        $detail = 'reachable via '.$target;

        // A slow handshake is paid twice by a restore — once for the safety dump,
        // once for the restore itself — so it is the usual answer to "why is this
        // spinner still going".
        if ($elapsed > 1.0) {
            $detail .= ' — unusually slow, this is what makes restores drag';
        }

        return $this->check('Database connection', self::PASS, $detail, $elapsed);
    }

    private function databaseSize(): ?array
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
            return null;
        }

        [$kb, $tables] = array_pad(preg_split('/\s+/', trim($process->getOutput())), 2, '?');

        return $this->check('Database size', self::PASS, "{$kb} KB across {$tables} tables", $elapsed);
    }

    /**
     * @return list<array{label: string, status: string, detail: string, elapsed: float|null}>
     */
    private function limits(): array
    {
        return [
            $this->check('Process timeout', self::PASS, config('backup.process_timeout').'s per mysql/mysqldump call'),
            $this->check('Connect timeout', self::PASS, config('backup.connect_timeout').'s before giving up on the DB host'),
            $this->check('PHP max_execution_time', self::PASS, (ini_get('max_execution_time') ?: '0').'s (0 = unlimited)'),
        ];
    }

    /**
     * @return array{label: string, status: string, detail: string, elapsed: float|null}
     */
    private function check(string $label, string $status, string $detail, ?float $elapsed = null): array
    {
        return compact('label', 'status', 'detail', 'elapsed');
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
            // A timeout, or a disabled proc_open, still needs to produce a Process
            // for the caller to inspect — so swallow and let isSuccessful() speak.
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
        if ($this->optionFilePath !== null) {
            return $this->optionFilePath;
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

        return $this->optionFilePath = $path;
    }

    /**
     * The option file holds the database password, so it never outlives the report.
     */
    private function cleanUp(): void
    {
        if ($this->optionFilePath !== null) {
            @unlink($this->optionFilePath);
            $this->optionFilePath = null;
        }
    }
}
