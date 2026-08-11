<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeStatusChange extends Model
{
    use Auditable;

    protected $table = 'employee_status_changes';

    protected $fillable = [
        'employee_id',
        'is_active',
        'effective_from',
        'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'effective_from' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
