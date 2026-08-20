<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class HourlyAttendance extends Model
{
    // Attendance is the most-edited thing in the system and was the one part
    // not recorded, so "who marked this week?" had no answer. It does now.
    use Auditable;

    protected $fillable = [
        'employee_id',
        'year',
        'week_number',
        'hours_map',        // json map: mon..sun => hours
        'ot_map',           // json map: mon..sun => ot hours
        'total_hours',
        'overtime_hours',
        'locked',
    ];

    protected $casts = [
        'hours_map' => 'array',
        'ot_map' => 'array',
        'locked' => 'boolean',
        'total_hours' => 'float',
        'overtime_hours' => 'float',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
