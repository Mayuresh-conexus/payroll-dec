<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A spell of leave taken by one employee, stored as an inclusive date range.
 *
 * The range is what the admin entered; how much of it is actually deducted and
 * paid is decided by LeaveService, which skips the employee's non-working days,
 * anything after their leaving date, and any day already marked present.
 */
class Leave extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'leaves';

    protected $fillable = [
        'employee_id',
        'start_date',
        'end_date',
        'hours_per_day',
        'reason',
        'created_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'hours_per_day' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Leave touching any part of the given inclusive date range.
     */
    public function scopeOverlapping(Builder $query, \DateTimeInterface $from, \DateTimeInterface $to): Builder
    {
        return $query
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from);
    }

    /**
     * The number of calendar days the range covers, working days included or not.
     */
    public function calendarDays(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date) + 1;
    }
}
