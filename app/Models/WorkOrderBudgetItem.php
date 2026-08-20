<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderBudgetItem extends Model
{
    protected $fillable = [
        'work_order_id', 'category', 'name', 'brand', 'size', 'unit',
        'quantity', 'hours', 'rate', 'vendor', 'amount',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'hours' => 'decimal:2',
            'rate' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
