<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LabourEntry extends Model
{
    protected $fillable = ['work_order_id', 'employee_id', 'labour_type', 'count', 'wage_rate', 'hours', 'amount', 'entry_date', 'added_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
