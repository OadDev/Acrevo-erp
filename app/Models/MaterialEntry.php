<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialEntry extends Model
{
    protected $fillable = ['work_order_id', 'material_name', 'brand', 'size', 'unit', 'quantity', 'rate', 'amount', 'vendor', 'entry_date', 'added_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
