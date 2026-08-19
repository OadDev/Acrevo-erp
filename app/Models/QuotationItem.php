<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuotationItem extends Model
{
    protected $fillable = [
        'quotation_id', 'item_type', 'name', 'description', 'unit',
        'quantity', 'unit_price', 'discount', 'tax_percent', 'total', 'sort_order',
    ];

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function recalculateTotal(): void
    {
        $this->total = round(($this->quantity * $this->unit_price) - $this->discount, 2);
    }
}
