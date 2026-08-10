<?php

use App\Models\BackupSchedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fires every minute but only actually runs a backup when the stored
// BackupSchedule settings say it's due — requires a real server cron entry
// (`* * * * * php artisan schedule:run`) to tick at all.
Schedule::command('backup:run --reason=scheduled')
    ->everyMinute()
    ->when(fn () => BackupSchedule::current()->isDueNow())
    ->withoutOverlapping(600);
