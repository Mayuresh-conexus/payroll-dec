<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollItem extends Model
{
    protected $fillable = [
        'payroll_run_id',
        'employee_id',
        'type',
        'present_days',
        'total_days',
        'total_hours',
        'overtime_hours',
        'gross_amount',
        'cash_amount',
        'bank_amount',
        'is_paid',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }
}
