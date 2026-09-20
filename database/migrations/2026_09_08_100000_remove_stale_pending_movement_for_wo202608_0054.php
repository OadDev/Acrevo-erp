<?php

use App\Models\AssetMovement;
use App\Models\AssetStock;
use App\Models\WorkOrder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * One-time cleanup, requested directly: WO-202608-0054's "Waiting for
     * Confirmation" list was showing a movement left over from testing the
     * asset-quantity-ledger bug fixes earlier today (2026-09-08) - a
     * pending movement created before the ledger existed, so it never had
     * a real reservation behind it. Removes exactly that stale entry the
     * same way the Movement History "Remove" button does (releasing
     * whatever reservation actually exists, which for a record like this
     * is nothing), leaving every other movement/work order untouched.
     * Safe to run more than once - a no-op once the row is gone.
     */
    public function up(): void
    {
        $workOrder = WorkOrder::where('work_order_no', 'WO-202608-0054')->first();

        if (! $workOrder) {
            return;
        }

        $movements = AssetMovement::where('to_work_order_id', $workOrder->id)
            ->where('status', 'pending')
            ->get();

        foreach ($movements as $movement) {
            $asset = \App\Models\Asset::withTrashed()->find($movement->asset_id);

            if ($asset) {
                $released = AssetStock::release($asset, $movement->to_location, $movement->to_work_order_id, 'in_transit', $movement->quantity);
                if ($released > 0) {
                    AssetStock::adjust($asset, $movement->from_location, $movement->from_work_order_id, 'available', $released);
                }
            }

            $movement->delete();
        }
    }

    public function down(): void
    {
        // Data cleanup, not reversible - the deleted movement's original
        // values aren't recoverable from here.
    }
};
