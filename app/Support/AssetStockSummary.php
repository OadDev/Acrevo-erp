<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\AssetStock;
use Illuminate\Support\Collection;

/**
 * Available/In Use/Ready for Return/Damaged/Missing/Total for one location
 * bucket (Company Store, or a specific work order) - the single source of
 * truth behind both the Company Store and WO Equipment pages, so the same
 * asset never reads differently depending on which page you're looking at
 * it from.
 *
 * "Total" deliberately excludes in_transit and under_repair - a movement
 * still awaiting confirmation (in_transit) or a repair still open
 * (under_repair) hasn't landed in this location's usable count yet, so
 * creating one must not inflate Total before it actually does.
 *
 * Both queries filter out any asset that's been removed (soft-deleted) -
 * a removed asset's historical stock no longer counts toward either the
 * stat cards or the asset-wise breakdown.
 *
 * Both queries also only count an asset here at all while it still holds
 * Available, In Use, or Ready for Return quantity at this exact location -
 * those three statuses all mean the asset genuinely hasn't left yet, just
 * in different operational states (checked out, awaiting pickup). Once
 * all three reach zero (a Movement took the remaining quantity elsewhere,
 * or the last such unit itself was marked damaged/missing), the asset is
 * no longer really "at" this location in any live sense, so any
 * Damaged/Missing quantity it left behind here stops counting too. That
 * history doesn't disappear - Verification History, Movement History, and
 * the asset's own Stock by Location card still show it - it just no longer
 * clutters a location's live equipment summary once nothing of that asset
 * is actually still there to act on. Changing a bucket's status via Update
 * Status never moves it to a different location, so it alone must never
 * make an asset disappear here - only a Movement actually relocating the
 * quantity does.
 */
class AssetStockSummary
{
    /**
     * The statuses that mean an asset's quantity is still genuinely here,
     * whatever operational state it's in - as opposed to having actually
     * left via a confirmed Movement, or sitting in a write-off state
     * (damaged/missing) with nothing else of it left to act on.
     */
    private const STILL_HERE_STATUSES = ['available', 'in_use', 'ready_for_return'];

    public static function totals(string $location, ?string $workOrderId = null): array
    {
        $assetIdsStillHere = self::assetIdsStillHere($location, $workOrderId);

        $byStatus = AssetStock::where('location', $location)
            ->where('work_order_id', $workOrderId)
            ->whereIn('asset_id', $assetIdsStillHere)
            ->selectRaw('status, sum(quantity) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $available = (int) ($byStatus['available'] ?? 0);
        $inUse = (int) ($byStatus['in_use'] ?? 0);
        $readyForReturn = (int) ($byStatus['ready_for_return'] ?? 0);
        $damaged = (int) ($byStatus['damaged'] ?? 0);
        $missing = (int) ($byStatus['missing'] ?? 0);

        return [
            'total' => $available + $inUse + $readyForReturn + $damaged + $missing,
            'available' => $available,
            'damaged' => $damaged,
            'missing' => $missing,
        ];
    }

    /**
     * Every asset_id currently holding Available, In Use, or Ready for
     * Return quantity at this exact location - the one inclusion test
     * totals() and byAsset() both apply, kept in one place so they can
     * never drift onto two different rules for "is this asset still here".
     */
    private static function assetIdsStillHere(string $location, ?string $workOrderId): Collection
    {
        return AssetStock::where('location', $location)
            ->where('work_order_id', $workOrderId)
            ->whereIn('status', self::STILL_HERE_STATUSES)
            ->where('quantity', '>', 0)
            ->whereHas('asset')
            ->pluck('asset_id');
    }

    /**
     * Ready for Return quantity for one location bucket, tracked as its
     * own call rather than folded into totals() - that return shape is
     * relied on elsewhere (e.g. the Company Store summary) as exactly
     * Total/Available/Damaged/Missing, so adding a status there would
     * silently change what "Total" means for every existing caller.
     */
    public static function readyForReturn(string $location, ?string $workOrderId = null): int
    {
        return (int) AssetStock::where('location', $location)
            ->where('work_order_id', $workOrderId)
            ->where('status', 'ready_for_return')
            ->whereHas('asset')
            ->sum('quantity');
    }

    /**
     * In Use quantity for one location bucket - same reasoning as
     * readyForReturn() above, kept separate from totals()'s fixed shape.
     */
    public static function inUse(string $location, ?string $workOrderId = null): int
    {
        return (int) AssetStock::where('location', $location)
            ->where('work_order_id', $workOrderId)
            ->where('status', 'in_use')
            ->whereHas('asset')
            ->sum('quantity');
    }

    /**
     * The same ledger as totals(), broken down per asset instead of
     * totalled across all of them - one row per Asset with its Total/
     * Available/In Use/Ready for Return/Damaged/Missing quantities in this
     * bucket, so a damaged or missing quantity can be traced back to
     * exactly which asset it belongs to.
     *
     * Driven by an Asset::query() ordered by name at the database level
     * (the same ORDER BY the asset list below it on the same page uses),
     * rather than grouping AssetStock rows and sorting the resulting
     * Collection in PHP - the two previously used different comparisons
     * (PHP's case-sensitive string sort vs. the database's own, usually
     * case-insensitive, collation), so this summary's order could drift
     * out of step with the list underneath it.
     */
    public static function byAsset(string $location, ?string $workOrderId = null, string $direction = 'asc')
    {
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        return Asset::query()
            ->whereIn('id', self::assetIdsStillHere($location, $workOrderId))
            ->with(['stocks' => fn ($q) => $q->where('location', $location)->where('work_order_id', $workOrderId)])
            ->orderBy('name', $direction)
            ->get()
            ->map(function (Asset $asset) {
                $byStatus = $asset->stocks->groupBy('status')->map(fn ($group) => $group->sum('quantity'));

                $available = (int) ($byStatus['available'] ?? 0);
                $inUse = (int) ($byStatus['in_use'] ?? 0);
                $readyForReturn = (int) ($byStatus['ready_for_return'] ?? 0);
                $damaged = (int) ($byStatus['damaged'] ?? 0);
                $missing = (int) ($byStatus['missing'] ?? 0);

                return (object) [
                    'asset' => $asset,
                    'total' => $available + $inUse + $readyForReturn + $damaged + $missing,
                    'available' => $available,
                    'in_use' => $inUse,
                    'ready_for_return' => $readyForReturn,
                    'damaged' => $damaged,
                    'missing' => $missing,
                ];
            });
    }
}
