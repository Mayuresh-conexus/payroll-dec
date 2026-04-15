<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

class PayrollRun extends Model
{
    use Auditable;
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
