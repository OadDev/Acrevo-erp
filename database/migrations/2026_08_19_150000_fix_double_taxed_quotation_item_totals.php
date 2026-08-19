<?php

use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Database\Migrations\Migration;

/**
 * QuotationController::syncItems() used to bake tax into each item's
 * `total`, and then Quotation::recalculateTotals() applied tax again on
 * top of the (already tax-inclusive) subtotal - double-taxing every
 * quotation created before this fix. This backs each item's stored total
 * out to its pre-tax value using the tax_percent already stored on that
 * same item, then recalculates the parent quotation's subtotal/tax/total.
 */
return new class extends Migration
{
    public function up(): void
    {
        QuotationItem::whereNotNull('quotation_id')->chunkById(200, function ($items) {
            foreach ($items as $item) {
                if ((float) $item->tax_percent > 0) {
                    $item->updateQuietly([
                        'total' => round((float) $item->total / (1 + ((float) $item->tax_percent / 100)), 2),
                    ]);
                }
            }
        });

        Quotation::withTrashed()->whereHas('items')->chunkById(100, function ($quotations) {
            foreach ($quotations as $quotation) {
                $quotation->recalculateTotals();
            }
        });
    }

    public function down(): void
    {
        // Not reversible - the original (double-taxed) totals aren't recoverable.
    }
};
