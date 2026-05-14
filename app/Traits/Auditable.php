<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

/**
 * Auditable Trait
 *
 * Attach to any Eloquent model to automatically record
 * create / update / delete events in the audit_logs table.
 *
 * Usage:
 *   use App\Traits\Auditable;
 *   class Employee extends Model { use Auditable; }
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(function (Model $model) {
            self::writeAudit('created', $model, [], $model->getAttributes());
        });

        static::updated(function (Model $model) {
            self::writeAudit('updated', $model, $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function (Model $model) {
            self::writeAudit('deleted', $model, $model->getAttributes(), []);
        });
    }

    private static function writeAudit(
        string $action,
        Model  $model,
        array  $oldValues,
        array  $newValues
    ): void {
        // Strip sensitive fields
        $hidden    = $model->getHidden();
        $oldValues = array_diff_key($oldValues, array_flip($hidden));
        $newValues = array_diff_key($newValues, array_flip($hidden));

        try {
            AuditLog::create([
                'user_id'    => auth()->id(),
                'action'     => $action,
                'model_type' => class_basename($model),
                'model_id'   => $model->getKey(),
                'old_values' => $oldValues ?: null,
                'new_values' => $newValues ?: null,
                'ip_address' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
            // Never let audit failures crash a business operation.
            // Log the error so it can be diagnosed without blocking the user.
            logger()->error('Audit write failed: ' . $e->getMessage(), [
                'action'     => $action,
                'model_type' => class_basename($model),
                'model_id'   => $model->getKey(),
            ]);
        }
    }
}
