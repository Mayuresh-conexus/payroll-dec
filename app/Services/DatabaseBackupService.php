<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * DatabaseBackupService
 *
 * Shells out to mysqldump/mysql (via Symfony Process, never a shell string —
 * array-form commands avoid shell injection) to create, list, restore, and
 * prune gzip-compressed MySQL backups on the 'local' (non-web-accessible) disk.
 *
 * Filename convention: {type}_{Ymd_His}.sql.gz, where type is
 * manual | scheduled | prerestore.
 */
class DatabaseBackupService
{
    private const FILENAME_PATTERN = '/^[A-Za-z0-9_\-]+\.sql\.gz$/';

    private bool $preflightPassed = false;

    public function create(string $type = 'manual'): array
    {
        $this->preflight();

        $directory = config('backup.directory');
        Storage::disk('local')->makeDirectory($directory);

        $filename = sprintf('%s_%s.sql.gz', $type, now()->format('Ymd_His'));
        $destPath = Storage::disk('local')->path("{$directory}/{$filename}");

        $optionFile = $this->writeMysqlOptionFile();

        try {
            $this->runDump($optionFile, $destPath);
        } finally {
            @unlink($optionFile);
        }

        clearstatcache(true, $destPath);

        return [
            'filename' => $filename,
            'size_bytes' => filesize($destPath),
            'created_at' => now(),
        ];
    }

    public function list(): Collection
    {
        $disk = Storage::disk('local');
        $directory = config('backup.directory');

        if (! $disk->exists($directory)) {
            return collect();
        }

        return collect($disk->files($directory))
            ->filter(fn (string $path): bool => str_ends_with($path, '.sql.gz'))
            ->map(function (string $path) use ($disk): array {
                $filename = basename($path);
                $size = $disk->size($path);

                return [
                    'filename' => $filename,
                    'size_bytes' => $size,
                    'size_human' => $this->formatBytes($size),
                    'created_at' => Carbon::createFromTimestamp($disk->lastModified($path)),
                    'type' => $this->inferType($filename),
                ];
            })
            ->sortByDesc('created_at')
            ->values();
    }

    public function delete(string $filename): void
    {
        if (! $this->isValidFilename($filename)) {
            throw new InvalidArgumentException('Invalid backup filename.');
        }

        $path = config('backup.directory')."/{$filename}";

        if (! Storage::disk('local')->exists($path)) {
            throw new InvalidArgumentException("Backup file not found: {$filename}");
        }

        Storage::disk('local')->delete($path);
    }

    public function restore(string $filename): array
    {
        if (! $this->isValidFilename($filename)) {
            throw new InvalidArgumentException('Invalid backup filename.');
        }

        $directory = config('backup.directory');

        if (! Storage::disk('local')->exists("{$directory}/{$filename}")) {
            throw new InvalidArgumentException("Backup file not found: {$filename}");
        }

        // Probe before the safety dump, not after: a restore runs two client
        // processes back to back, so an unreachable database would otherwise
        // stall twice over before reporting anything.
        $this->preflight();

        // Always take a fresh safety snapshot before touching the live database.
        // If this fails, propagate immediately — never restore without a fresh net.
        $pre = $this->create('prerestore');

        $sourcePath = Storage::disk('local')->path("{$directory}/{$filename}");
        $optionFile = $this->writeMysqlOptionFile();

        try {
            $this->runRestore($optionFile, $sourcePath);
        } finally {
            @unlink($optionFile);
        }

        return ['pre_restore_filename' => $pre['filename']];
    }

    public function pruneExpired(int $retentionDays): int
    {
        $cutoff = now()->subDays($retentionDays);
        $removed = 0;

        foreach ($this->list() as $backup) {
            if ($backup['created_at']->lt($cutoff)) {
                $this->delete($backup['filename']);
                $removed++;
            }
        }

        return $removed;
    }

    public function isValidFilename(string $filename): bool
    {
        return (bool) preg_match(self::FILENAME_PATTERN, $filename);
    }

    /**
     * Verify the database is actually reachable before starting real work.
     *
     * mysqldump has no connect-timeout option of its own, so on a host where the
     * database is unreachable it blocks on the kernel's TCP retry budget (~2
     * minutes) instead of failing. A restore pays that twice — once for the
     * safety dump, once for the restore — which surfaces as a spinner that hangs
     * for four minutes and then reports a generic timeout. Probing first with the
     * mysql client, which does honour connect-timeout, turns that into a bounded
     * and specific error before anything has been written.
     */
    private function preflight(): void
    {
        if ($this->preflightPassed) {
            return;
        }

        $this->assertProcessFunctionsAvailable();

        $optionFile = $this->writeMysqlOptionFile();

        try {
            $this->assertDatabaseReachable($optionFile);
        } finally {
            @unlink($optionFile);
        }

        $this->preflightPassed = true;
    }

    /**
     * Shared hosting commonly disables proc_open, which Symfony Process needs.
     * Without this check the failure surfaces as an opaque fatal error deep in
     * the vendor stack rather than something an admin can act on.
     */
    private function assertProcessFunctionsAvailable(): void
    {
        $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));

        foreach (['proc_open', 'proc_close'] as $function) {
            if (! function_exists($function) || in_array($function, $disabled, true)) {
                throw new RuntimeException(
                    "PHP's {$function}() is disabled on this server, so backups cannot run. "
                    .'Remove it from disable_functions in php.ini (or ask your host to).'
                );
            }
        }
    }

    private function assertDatabaseReachable(string $optionFile): void
    {
        $connectTimeout = (int) config('backup.connect_timeout', 10);

        $process = new Process([
            config('backup.mysql_path'),
            '--defaults-extra-file='.$optionFile,
            '--batch',
            '--skip-column-names',
            '--execute=SELECT 1',
            $this->databaseName(),
        ]);

        // Slightly above the client's own connect-timeout so the client reports
        // the specific reason rather than us reporting a blunt process timeout.
        $process->setTimeout($connectTimeout + 5);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw new RuntimeException(
                "Could not reach the database within {$connectTimeout}s. "
                .'Check the DB host, port and credentials in .env.'
            );
        }

        if (! $process->isSuccessful()) {
            $reason = trim($process->getErrorOutput()) ?: trim($process->getOutput());

            if ($process->getExitCode() === 127) {
                throw new RuntimeException(
                    'The "'.config('backup.mysql_path').'" command was not found on this server. '
                    .'Set MYSQL_PATH and MYSQLDUMP_PATH in .env to their full paths.'
                );
            }

            throw new RuntimeException('Database connection check failed: '.$reason);
        }
    }

    private function runDump(string $optionFile, string $destPath): void
    {
        $command = [
            config('backup.mysqldump_path'),
            '--defaults-extra-file='.$optionFile,
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--events',
            '--no-tablespaces',
            '--default-character-set=utf8mb4',
        ];

        // Leave runtime tables out entirely. Because they are absent from the dump,
        // a restore also leaves them alone — the admin's session and CSRF token
        // survive the operation instead of being swapped mid-request.
        foreach ((array) config('backup.excluded_tables', []) as $table) {
            $command[] = '--ignore-table='.$this->databaseName().'.'.$table;
        }

        // MariaDB's mysqldump doesn't recognize --set-gtid-purged (it predates MySQL's
        // GTID_PURGED variable and uses its own replication model), so only pass it when
        // the binary actually advertises support — otherwise the dump aborts immediately.
        if ($this->mysqldumpSupportsGtidPurged()) {
            $command[] = '--set-gtid-purged=OFF';
        }

        $command[] = $this->databaseName();

        $process = new Process($command);
        $process->setTimeout(config('backup.process_timeout'));

        $gz = @gzopen($destPath, 'wb9');
        if ($gz === false) {
            throw new RuntimeException("Unable to open {$destPath} for writing.");
        }

        $errorOutput = '';

        try {
            $process->run(function (string $type, string $buffer) use ($gz, &$errorOutput): void {
                if ($type === Process::OUT) {
                    gzwrite($gz, $buffer);
                } else {
                    $errorOutput .= $buffer;
                }
            });
        } catch (ProcessTimedOutException $e) {
            gzclose($gz);
            @unlink($destPath);
            throw new RuntimeException('mysqldump timed out after '.config('backup.process_timeout').' seconds.');
        }

        gzclose($gz);

        if (! $process->isSuccessful()) {
            @unlink($destPath);
            throw new RuntimeException('mysqldump failed: '.($errorOutput !== '' ? $errorOutput : $process->getErrorOutput()));
        }
    }

    private function runRestore(string $optionFile, string $sourcePath): void
    {
        $process = new Process([
            config('backup.mysql_path'),
            '--defaults-extra-file='.$optionFile,
            $this->databaseName(),
        ]);
        $process->setTimeout(config('backup.process_timeout'));
        $process->setInput($this->streamGzipFile($sourcePath));

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            throw new RuntimeException('mysql restore timed out after '.config('backup.process_timeout').' seconds.');
        }

        if (! $process->isSuccessful()) {
            $error = trim($process->getErrorOutput());

            if (str_contains($error, 'Lock wait timeout') || str_contains($error, 'metadata lock')) {
                throw new RuntimeException(
                    'Restore could not get exclusive access to the tables — another session is holding them open. '
                    .'Ask other users to leave the app, then try again. ('.$error.')'
                );
            }

            throw new RuntimeException('mysql restore failed: '.$error);
        }
    }

    private function streamGzipFile(string $path): \Generator
    {
        $gz = gzopen($path, 'rb');
        if ($gz === false) {
            throw new RuntimeException("Unable to open {$path} for reading.");
        }

        yield $this->restoreSessionPrologue();

        try {
            while (! gzeof($gz)) {
                yield gzread($gz, 1024 * 1024);
            }
        } finally {
            gzclose($gz);
        }
    }

    /**
     * Bound how long the restore will wait for table locks.
     *
     * A restore drops and recreates every table, which needs an exclusive
     * metadata lock. MySQL's lock_wait_timeout defaults to 31536000 seconds — a
     * full year — so a single other session with an open transaction on one of
     * those tables stalls the restore indefinitely with no output at all. That
     * is invisible on a quiet local machine and routine on a live server with
     * other users logged in. Failing after a bounded wait turns a silent hang
     * into an error the admin can act on.
     */
    private function restoreSessionPrologue(): string
    {
        $seconds = (int) config('backup.lock_wait_timeout', 30);

        return "SET SESSION lock_wait_timeout = {$seconds};\n"
            ."SET SESSION innodb_lock_wait_timeout = {$seconds};\n";
    }

    private function writeMysqlOptionFile(): string
    {
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

        // connect-timeout must be scoped to [mysql] rather than [client]:
        // mysqldump aborts with "unknown variable 'connect-timeout'" if it sees
        // one, the same way it does for --set-gtid-purged. Options under a
        // binary-specific section are only read by that binary.
        $lines[] = '';
        $lines[] = '[mysql]';
        $lines[] = 'connect-timeout='.(int) config('backup.connect_timeout', 10);

        $path = tempnam(sys_get_temp_dir(), 'mysqlopt_');
        if ($path === false) {
            throw new RuntimeException('Unable to create a temporary MySQL option file.');
        }

        file_put_contents($path, implode("\n", $lines).PHP_EOL);
        chmod($path, 0600);

        return $path;
    }

    private function mysqldumpSupportsGtidPurged(): bool
    {
        static $supported = null;

        if ($supported !== null) {
            return $supported;
        }

        $process = new Process([config('backup.mysqldump_path'), '--help']);
        $process->setTimeout(15);

        try {
            $process->run();
        } catch (ProcessTimedOutException $e) {
            return $supported = false;
        }

        return $supported = str_contains($process->getOutput(), 'set-gtid-purged');
    }

    private function databaseName(): string
    {
        return config('database.connections.mysql.database');
    }

    private function inferType(string $filename): string
    {
        return match (true) {
            str_starts_with($filename, 'manual_') => 'manual',
            str_starts_with($filename, 'scheduled_') => 'scheduled',
            str_starts_with($filename, 'prerestore_') => 'prerestore',
            default => 'unknown',
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return "{$bytes} B";
        }

        $value = $bytes;
        foreach (['KB', 'MB', 'GB', 'TB'] as $unit) {
            $value /= 1024;
            if ($value < 1024) {
                return round($value, 1)." {$unit}";
            }
        }

        return round($value, 1).' TB';
    }
}
