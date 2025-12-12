<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Employee extends Model
{
    use HasFactory, SoftDeletes;

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
        'is_active',
    ];

    protected $casts = [
        'joining_date' => 'date',
        'is_active' => 'boolean',
        'hours_per_day' => 'float',
    ];
}
