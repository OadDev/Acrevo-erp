<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderAsset extends Model
{
    protected $fillable = [
        'work_order_id', 'asset_name', 'allocated_quantity', 'damaged_quantity', 'missing_quantity', 'remarks', 'created_by',
    ];

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function inUseQuantity(): int
    {
        return $this->allocated_quantity - $this->damaged_quantity - $this->missing_quantity;
    }
}
