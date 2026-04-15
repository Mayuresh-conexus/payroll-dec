<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use App\Models\EmployeeRate;
use App\Traits\Auditable;

class Employee extends Model
{
    use HasFactory, SoftDeletes, Auditable;

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

    public function rates()
    {
        return $this->hasMany(EmployeeRate::class);
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
