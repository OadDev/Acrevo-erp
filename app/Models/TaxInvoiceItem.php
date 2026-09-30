<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxInvoiceItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['tax_invoice_id', 'item_id', 'name', 'hsn_sac_code', 'unit', 'quantity', 'rate', 'total', 'sort_order'];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'rate' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function taxInvoice(): BelongsTo
    {
        return $this->belongsTo(TaxInvoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
