<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * BackupSchedule
 *
 * Single-row configuration for the recurring database backup schedule.
 * Use BackupSchedule::current() rather than querying directly.
 *
 * @property int $id
 * @property bool $enabled
 * @property string $frequency daily | weekly
 * @property string $run_at H:i:s
 * @property int|null $day_of_week 0 (Sunday) .. 6 (Saturday), only used when frequency = weekly
 * @property int|null $retention_days
 * @property \Carbon\Carbon|null $last_run_at
 * @property int|null $updated_by
 */
class BackupSchedule extends Model
{
    protected $fillable = [
        'enabled',
        'frequency',
        'run_at',
        'day_of_week',
        'retention_days',
        'last_run_at',
        'updated_by',
    ];

    protected $casts = [
        'enabled' => 'boolean',
        'day_of_week' => 'integer',
        'retention_days' => 'integer',
        'last_run_at' => 'datetime',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'enabled' => false,
            'frequency' => 'daily',
            'run_at' => '02:00:00',
            'retention_days' => 30,
        ]);
    }

    public function isDueNow(): bool
    {
        if (! $this->enabled) {
            return false;
        }

        if (now()->format('H:i') !== Carbon::parse($this->run_at)->format('H:i')) {
            return false;
        }

        if ($this->frequency === 'weekly' && now()->dayOfWeek !== $this->day_of_week) {
            return false;
        }

        if ($this->last_run_at && $this->last_run_at->isSameDay(now())) {
            return false;
        }

        return true;
    }

    public function markRan(): void
    {
        $this->update(['last_run_at' => now()]);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
