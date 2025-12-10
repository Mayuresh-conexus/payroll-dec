<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyRateAttendance extends Model
{
    protected $fillable = [
        'employee_id',
        'year',
        'week_number',
        'total_working_days',
        'present_days',
        'locked',
        'days_map',
    ];

     protected $casts = [
        'days_map' => 'array',
    ];
    
public function employee()
{
    return $this->belongsTo(Employee::class);
}
}

