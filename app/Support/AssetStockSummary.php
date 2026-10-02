<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\AssetStock;
use Illuminate\Support\Collection;

/**
 * Available/Damaged/Missing/Total for one location bucket (Company Store,
 * or a specific work order) - the single source of truth behind both the
 * Company Store and WO Equipment pages, so the same asset never reads
 * differently depending on which page you're looking at it from.
 *
 * "Total" deliberately excludes in_transit and under_repair - it always
 * reconciles to exactly Available + Damaged + Missing, the only three
 * states either page breaks out. A movement still awaiting confirmation
 * (in_transit) or a repair still open (under_repair) hasn't landed in this
 * location's usable count yet, so creating one must not inflate Total
 * before it actually does.
 *
 * Both queries filter out any asset that's been removed (soft-deleted) -
 * a removed asset's historical stock no longer counts toward either the
 * stat cards or the asset-wise breakdown.
 *
 * Both queries also only count an asset here at all while it still holds
 * Available quantity at this exact location - once that reaches zero (a
 * Movement took the available portion elsewhere, or the last available
 * unit itself was marked damaged/missing), the asset is no longer really
 * "at" this location in any live sense, so any Damaged/Missing quantity
 * it left behind here stops counting too. That history doesn't disappear -
 * Verification History, Movement History, and the asset's own Stock by
 * Location card still show it - it just no longer clutters a location's
 * live equipment summary once nothing of that asset is actually still
 * there to act on.
 */
class AssetStockSummary
{
    public static function totals(string $location, ?string $workOrderId = null): array
    {
        $assetIdsStillHere = self::assetIdsWithAvailableStock($location, $workOrderId);

        $byStatus = AssetStock::where('location', $location)
            ->where('work_order_id', $workOrderId)
            ->whereIn('asset_id', $assetIdsStillHere)
            ->selectRaw('status, sum(quantity) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $available = (int) ($byStatus['available'] ?? 0);
        $damaged = (int) ($byStatus['damaged'] ?? 0);
        $missing = (int) ($byStatus['missing'] ?? 0);

        return [
            'total' => $available + $damaged + $missing,
            'available' => $available,
            'damaged' => $damaged,
            'missing' => $missing,
        ];
    }

    /**
     * Every asset_id currently holding Available quantity at this exact
     * location - the one inclusion test totals() and byAsset() both apply,
     * kept in one place so they can never drift onto two different rules
     * for "is this asset still here".
     */
    private static function assetIdsWithAvailableStock(string $location, ?string $workOrderId): Collection
    {
        return AssetStock::where('location', $location)
            ->where('work_order_id', $workOrderId)
            ->where('status', 'available')
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
     * The same ledger as totals(), broken down per asset instead of
     * totalled across all of them - one row per Asset with its Total/In
     * Use (Available)/Damaged/Missing quantities in this bucket, so a
     * damaged or missing quantity can be traced back to exactly which
     * asset it belongs to.
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
            ->whereIn('id', self::assetIdsWithAvailableStock($location, $workOrderId))
            ->with(['stocks' => fn ($q) => $q->where('location', $location)->where('work_order_id', $workOrderId)])
            ->orderBy('name', $direction)
            ->get()
            ->map(function (Asset $asset) {
                $byStatus = $asset->stocks->groupBy('status')->map(fn ($group) => $group->sum('quantity'));

                $inUse = (int) ($byStatus['available'] ?? 0);
                $damaged = (int) ($byStatus['damaged'] ?? 0);
                $missing = (int) ($byStatus['missing'] ?? 0);

                return (object) [
                    'asset' => $asset,
                    'total' => $inUse + $damaged + $missing,
                    'in_use' => $inUse,
                    'damaged' => $damaged,
                    'missing' => $missing,
                ];
            });
    }
}
