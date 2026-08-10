<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class BackupRestoreCommand extends Command
{
    protected $signature = 'backup:restore {filename : The .sql.gz backup filename to restore}
        {--force : Skip the interactive confirmation prompt (used by the web UI, which already confirmed)}';

    protected $description = 'Restore the MySQL database from a backup file, taking a fresh safety backup first';

    public function handle(DatabaseBackupService $service): int
    {
        $filename = $this->argument('filename');

        if (! $service->isValidFilename($filename)) {
            $this->error('Invalid backup filename.');

            return self::FAILURE;
        }

        if (! $this->option('force') && ! $this->confirm('This will overwrite the live database. A safety backup will be taken first. Continue?')) {
            $this->warn('Aborted.');

            return self::FAILURE;
        }

        try {
            $result = $service->restore($filename);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Pre-restore safety backup: {$result['pre_restore_filename']}");
        $this->info('Restore complete.');

        return self::SUCCESS;
    }
}
