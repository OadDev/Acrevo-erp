<?php

namespace App\Models\Concerns;

/**
 * Shared subtotal/tax/total recompute for Proforma Invoice and Tax
 * Invoice - a single document-level tax_percent applied once to the
 * summed (pre-tax) line totals, matching the sample Tax Invoice format
 * (Taxable Value / VAT % / Invoice Total). No discount line - the sample
 * documents don't carry one.
 */
trait HasBillingTotals
{
    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->sum('total');
        $tax = round($subtotal * ($this->tax_percent / 100), 2);

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $subtotal + $tax,
        ]);
    }
}
