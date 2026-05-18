<?php

namespace App\Models;

use App\Traits\Auditable;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use Auditable, HasFactory, SoftDeletes;

    protected $table = 'employees';

    protected $fillable = [
        'employee_code',
        'name',
        'joining_date',
        'department',
        'bank_name',
        'bank_account',
        'bank_ifsc',
        'type',
        'daily_rate',
        'hourly_rate',
        'hours_per_day',
        'bank_transfer_fix_amount',
        'weekly_active_days',
        'is_active',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'is_active' => 'boolean',
        'hours_per_day' => 'float',
    ];

    /** The manager user account linked to this employee, if any. */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    public function rates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeRate::class);
    }

    public function managers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'manager_employee', 'employee_id', 'manager_id')
            ->withPivot(['assigned_by', 'assigned_at'])
            ->withTimestamps();
    }

    public function scopeForManager(Builder $query, int $managerId): Builder
    {
        return $query->whereHas('managers', fn (Builder $q) => $q->where('users.id', $managerId));
    }

    /**
     * Get the most recent rate amount for a given type from the rate history.
     * Falls back to the denormalized column on the employee if no history exists.
     * $type: 'daily_rate' | 'hourly_rate' | 'hours_per_day'
     */
    public function latestRateOf(string $type): ?float
    {
        // If rates are already eager-loaded, avoid a new query
        if ($this->relationLoaded('rates')) {
            $rate = $this->rates->where('rate_type', $type)->first();

            return $rate ? (float) $rate->amount : (float) ($this->{$type} ?? 0);
        }

        $rate = $this->rates()
            ->where('rate_type', $type)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        return $rate ? (float) $rate->amount : (float) ($this->{$type} ?? 0);
    }

    /**
     * Get effective rate for given date and type.
     * $type: daily_rate | hourly_rate | hours_per_day
     */
    public function rateAt(\DateTimeInterface $date, string $type)
    {
        $d = Carbon::instance($date)->toDateString();

        $rate = $this->rates()
            ->where('rate_type', $type)
            ->where(function ($q) use ($d) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $d);
            })
            ->where(function ($q) use ($d) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $d);
            })
            ->orderByDesc('effective_from')
            ->first();

        return $rate ? (float) $rate->amount : null;
    }

    protected static function booted()
    {
        // Model events can be registered here
    }
}
