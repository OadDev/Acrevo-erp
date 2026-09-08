<?php

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetRepair;
use App\Models\AssetStock;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Removing an asset (Asset::destroy(), a soft delete) never touched
     * its still-pending movements or still-open repairs, so those kept
     * showing up - e.g. on a work order's "Waiting for Confirmation" list -
     * with no way to clear them short of a database fix, since Confirm/
     * Cancel/status-update all need the asset to still resolve. This is a
     * one-time sweep of every such orphan already sitting in production,
     * cleaned up the same way the Movement/Repair "Remove" buttons do
     * (releasing whatever ledger reservation actually exists, which is
     * often nothing for anything created before the quantity ledger).
     *
     * Going forward, permanently deleting an asset (the new "Remove" on
     * the Removed Assets list) cascade-deletes these at the database
     * level, so this class of orphan won't reappear for any asset that's
     * force-deleted after this. Safe to run more than once.
     */
    public function up(): void
    {
        $trashedAssetIds = Asset::onlyTrashed()->pluck('id');

        if ($trashedAssetIds->isEmpty()) {
            return;
        }

        AssetMovement::whereIn('asset_id', $trashedAssetIds)
            ->where('status', 'pending')
            ->get()
            ->each(function (AssetMovement $movement) {
                $asset = Asset::withTrashed()->find($movement->asset_id);

                if ($asset) {
                    $released = AssetStock::release($asset, $movement->to_location, $movement->to_work_order_id, 'in_transit', $movement->quantity);
                    if ($released > 0) {
                        AssetStock::adjust($asset, $movement->from_location, $movement->from_work_order_id, 'available', $released);
                    }
                }

                $movement->delete();
            });

        AssetRepair::whereIn('asset_id', $trashedAssetIds)
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->get()
            ->each(function (AssetRepair $repair) {
                $asset = Asset::withTrashed()->find($repair->asset_id);

                if ($asset) {
                    $released = AssetStock::release($asset, $repair->location, $repair->work_order_id, 'under_repair', $repair->quantity);
                    if ($released > 0) {
                        AssetStock::adjust($asset, $repair->location, $repair->work_order_id, 'available', $released);
                    }
                }

                $repair->delete();
            });
    }

    public function down(): void
    {
        // Data cleanup, not reversible - the deleted records' original
        // values aren't recoverable from here.
    }
};
