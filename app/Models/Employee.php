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
        'bh_bank_percent',
        'weekly_active_days',
        'is_active',
        'deactivated_at',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'deactivated_at' => 'date',
        'is_active' => 'boolean',
        'hours_per_day' => 'float',
        'bh_bank_percent' => 'float',
    ];

    /**
     * Working days in a standard week when weekly_active_days was never set.
     *
     * Six matches the Mon–Sat week the attendance grid already assumes when it
     * stores total_working_days.
     */
    public const DEFAULT_WORKING_DAYS_PER_WEEK = 6;

    /**
     * How many days of the week this employee is expected to work.
     */
    public function workingDaysPerWeek(): int
    {
        $days = (int) ($this->weekly_active_days ?: self::DEFAULT_WORKING_DAYS_PER_WEEK);

        return max(1, min(7, $days));
    }

    /**
     * Whether a date is one of this employee's working days.
     *
     * The working week is counted forwards from Monday, so someone on a five-day
     * week works Mon–Fri and someone on six works Mon–Sat. Only the *count* of
     * working days is stored, so this is the rule that turns it into named days.
     */
    public function isWorkingDay(\DateTimeInterface $date): bool
    {
        return Carbon::instance($date)->dayOfWeekIso <= $this->workingDaysPerWeek();
    }

    /**
     * The first day of the leave year covering a given date (today by default).
     *
     * Leave years run from the joining anniversary, so everyone's entitlement
     * resets on the date they started. Employees with no joining date on file
     * fall back to the calendar year, which is the only sensible guess left.
     */
    public function leaveYearStart(?\DateTimeInterface $on = null): Carbon
    {
        $on = Carbon::instance($on ?? now())->startOfDay();

        if (! $this->joining_date) {
            return $on->copy()->startOfYear();
        }

        $joined = $this->joining_date->copy()->startOfDay();
        $anniversary = $this->anniversaryIn($joined, $on->year);

        if ($anniversary->gt($on)) {
            $anniversary = $this->anniversaryIn($joined, $on->year - 1);
        }

        // Someone's first leave year cannot start before they started.
        return $anniversary->lt($joined) ? $joined : $anniversary;
    }

    /**
     * The last day of the leave year covering a given date (today by default).
     */
    public function leaveYearEnd(?\DateTimeInterface $on = null): Carbon
    {
        return $this->leaveYearStart($on)->addYear()->subDay()->startOfDay();
    }

    /**
     * The joining anniversary in a given year, without spilling into the next
     * month — a 29 February start date lands on 28 February in ordinary years.
     */
    private function anniversaryIn(Carbon $joined, int $year): Carbon
    {
        $daysInMonth = Carbon::create($year, $joined->month, 1)->daysInMonth;

        return Carbon::create($year, $joined->month, min($joined->day, $daysInMonth))->startOfDay();
    }

    /**
     * Whether work done on a given date is payable.
     *
     * Deactivation takes effect from its own date, so the last payable day is the
     * one before it. Employees who were never deactivated are always payable.
     */
    public function isPaidOn(\DateTimeInterface $date): bool
    {
        if ($this->deactivated_at === null) {
            return true;
        }

        return Carbon::instance($date)->startOfDay()
            ->lt($this->deactivated_at->copy()->startOfDay());
    }

    /** The manager user account linked to this employee, if any. */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'employee_id');
    }

    public function rates(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeRate::class);
    }

    public function statusChanges(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(EmployeeStatusChange::class);
    }

    public function leaves(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Leave::class);
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
     * Employees with at least one payable day in the week beginning $weekStart:
     * still active, or deactivated after that week had already started.
     *
     * Filtering on is_active alone would erase a leaver from the past weeks they
     * actually worked, not just from the weeks after they left.
     */
    public function scopeActiveDuringWeek(Builder $query, \DateTimeInterface $weekStart): Builder
    {
        $start = Carbon::instance($weekStart)->toDateString();

        return $query->where(function (Builder $q) use ($start) {
            $q->where('is_active', true)
                ->orWhere(fn (Builder $inner) => $inner
                    ->whereNotNull('deactivated_at')
                    ->whereDate('deactivated_at', '>', $start));
        });
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
     * The rate history entry actually in effect on a given date (today by default).
     *
     * Unlike latestRateOf(), a future-dated entry is NOT considered — a rate
     * scheduled to start next week is not the rate in effect now.
     *
     * $type: daily_rate | hourly_rate | hours_per_day
     */
    public function rateEntryAt(string $type, ?\DateTimeInterface $date = null): ?EmployeeRate
    {
        $on = Carbon::instance($date ?? now())->toDateString();

        // Use the eager-loaded relation when present to avoid N+1 in list views.
        if ($this->relationLoaded('rates')) {
            return $this->rates
                ->filter(function (EmployeeRate $rate) use ($type, $on) {
                    if ($rate->rate_type !== $type) {
                        return false;
                    }
                    $from = $rate->effective_from ? Carbon::parse($rate->effective_from)->toDateString() : null;
                    $to = $rate->effective_to ? Carbon::parse($rate->effective_to)->toDateString() : null;

                    return ! ($from !== null && $from > $on) && ! ($to !== null && $to < $on);
                })
                // Sort defensively rather than trusting the eager-load's ordering.
                ->sort(fn (EmployeeRate $a, EmployeeRate $b) => [$b->effective_from, $b->id] <=> [$a->effective_from, $a->id])
                ->first();
        }

        return $this->rates()
            ->where('rate_type', $type)
            ->where(function ($q) use ($on) {
                $q->whereNull('effective_from')->orWhere('effective_from', '<=', $on);
            })
            ->where(function ($q) use ($on) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $on);
            })
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Rate amount in effect today, falling back to the denormalized column when
     * there is no applicable history entry.
     */
    public function currentRateOf(string $type): ?float
    {
        $entry = $this->rateEntryAt($type);

        return $entry ? (float) $entry->amount : (float) ($this->{$type} ?? 0);
    }

    /**
     * The next rate change scheduled to start after today, if one exists.
     */
    public function upcomingRateEntryOf(string $type): ?EmployeeRate
    {
        $today = now()->toDateString();

        if ($this->relationLoaded('rates')) {
            return $this->rates
                ->filter(fn (EmployeeRate $rate) => $rate->rate_type === $type
                    && $rate->effective_from
                    && Carbon::parse($rate->effective_from)->toDateString() > $today)
                ->sort(fn (EmployeeRate $a, EmployeeRate $b) => [$a->effective_from, $a->id] <=> [$b->effective_from, $b->id])
                ->first();
        }

        return $this->rates()
            ->where('rate_type', $type)
            ->whereNotNull('effective_from')
            ->where('effective_from', '>', $today)
            ->orderBy('effective_from')
            ->orderBy('id')
            ->first();
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
