<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeRate extends Model
{
    protected $table = 'employee_rates';

    protected $fillable = [
        'employee_id',
        'rate_type',
        'amount',
        'effective_from',
        'effective_to',
        'created_by',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}

