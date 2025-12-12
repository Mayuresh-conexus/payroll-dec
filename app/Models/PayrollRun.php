<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    protected $fillable = [
        'year',
        'week_number',
        'period_type',
        'month',
        'status',
        'created_by',
        'generated_at',
    ];

    public function items()
    {
        return $this->hasMany(PayrollItem::class);
    }
}
