<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryChallanItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['delivery_challan_id', 'item_id', 'name', 'hsn_sac_code', 'quantity', 'unit', 'sort_order'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function deliveryChallan(): BelongsTo
    {
        return $this->belongsTo(DeliveryChallan::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
