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
        'overtime_amount',
        'bh_amount',
        'bh_cash',
        'bh_bank',
        'bh_cash_override',
        'leave_days',
        'leave_hours',
        'leave_amount',
        'weekly_amount',
        'addons',
        'gross_amount',
        'cash_amount',
        'bank_amount',
        'applied_daily_rate',
        'applied_hourly_rate',
        'applied_hours_per_day',
        'applied_bh_bank_percent',
        'transfer_id',
        'transfer_date',
        'transfer_status',
        'note',
        'is_paid',
        'advance_given',
        'advance_recovered',
        'advance_balance',
    ];

    protected $casts = [
        'addons' => 'array',
        'weekly_amount' => 'decimal:2',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function run()
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    // Alias for consistency with controllers that eager-load payrollRun
    public function payrollRun()
    {
        return $this->run();
    }
}
