<?php

namespace App\Console\Commands;

use App\Models\BackupSchedule;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class BackupRunCommand extends Command
{
    protected $signature = 'backup:run {--reason=manual : manual|scheduled}';

    protected $description = 'Create a gzip-compressed MySQL database backup';

    public function handle(DatabaseBackupService $service): int
    {
        $reason = $this->option('reason');

        if (! in_array($reason, ['manual', 'scheduled'], true)) {
            $this->error('Invalid --reason. Expected "manual" or "scheduled".');

            return self::FAILURE;
        }

        try {
            $result = $service->create($reason);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Backup created: {$result['filename']} ({$result['size_bytes']} bytes)");

        if ($reason === 'scheduled') {
            $schedule = BackupSchedule::current();

            if ($schedule->retention_days) {
                $pruned = $service->pruneExpired($schedule->retention_days);
                $this->info("Pruned {$pruned} expired backup(s).");
            }

            $schedule->markRan();
        }

        return self::SUCCESS;
    }
}
