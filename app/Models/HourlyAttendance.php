<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HourlyAttendance extends Model
{
    protected $fillable = [
        'employee_id',
        'year',
        'week_number',
        'total_hours',
        'overtime_hours',
        'locked',
    ];
}
