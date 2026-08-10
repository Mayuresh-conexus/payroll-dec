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

    public function create(string $type = 'manual'): array
    {
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
            throw new RuntimeException('mysql restore failed: '.$process->getErrorOutput());
        }
    }

    private function streamGzipFile(string $path): \Generator
    {
        $gz = gzopen($path, 'rb');
        if ($gz === false) {
            throw new RuntimeException("Unable to open {$path} for reading.");
        }

        try {
            while (! gzeof($gz)) {
                yield gzread($gz, 1024 * 1024);
            }
        } finally {
            gzclose($gz);
        }
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
        $process->run();

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
