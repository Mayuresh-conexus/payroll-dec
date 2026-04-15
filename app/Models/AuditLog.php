<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * AuditLog
 *
 * Tracks every create / update / delete on audited models.
 *
 * @property int         $id
 * @property int|null    $user_id
 * @property string      $action       created | updated | deleted
 * @property string      $model_type   e.g. Employee
 * @property int|null    $model_id
 * @property array|null  $old_values
 * @property array|null  $new_values
 * @property string|null $ip_address
 * @property \Carbon\Carbon $created_at
 */
class AuditLog extends Model
{
    public $timestamps = false;  // only created_at needed

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'old_values',
        'new_values',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // Set created_at automatically since we have no updated_at
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (AuditLog $log) {
            $log->created_at = now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
