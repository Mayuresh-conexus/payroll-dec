<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class DailyRateAttendance extends Model
{
    // Attendance is the most-edited thing in the system and was the one part
    // not recorded, so "who marked this week?" had no answer. It does now.
    use Auditable;

    protected $fillable = [
        'employee_id',
        'year',
        'week_number',
        'total_working_days',
        'present_days',
        'locked',
        'days_map',
        'overtime_map',
        'overtime_amount',
    ];

    protected $casts = [
        'days_map' => 'array',
        'overtime_map' => 'array',
        'locked' => 'boolean',
        'overtime_amount' => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
