<?php

namespace App\Support;

use App\Models\AssetStock;

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
 */
class AssetStockSummary
{
    public static function totals(string $location, ?string $workOrderId = null): array
    {
        $byStatus = AssetStock::where('location', $location)
            ->where('work_order_id', $workOrderId)
            ->whereHas('asset')
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
     * The same ledger as totals(), broken down per asset instead of
     * totalled across all of them - one row per Asset with its Total/In
     * Use (Available)/Damaged/Missing quantities in this bucket, so a
     * damaged or missing quantity can be traced back to exactly which
     * asset it belongs to.
     */
    public static function byAsset(string $location, ?string $workOrderId = null)
    {
        return AssetStock::where('location', $location)
            ->where('work_order_id', $workOrderId)
            ->where('quantity', '>', 0)
            ->whereHas('asset')
            ->with('asset')
            ->get()
            ->groupBy('asset_id')
            ->map(function ($stocks) {
                $byStatus = $stocks->groupBy('status')->map(fn ($group) => $group->sum('quantity'));

                $inUse = (int) ($byStatus['available'] ?? 0);
                $damaged = (int) ($byStatus['damaged'] ?? 0);
                $missing = (int) ($byStatus['missing'] ?? 0);

                return (object) [
                    'asset' => $stocks->first()->asset,
                    'total' => $inUse + $damaged + $missing,
                    'in_use' => $inUse,
                    'damaged' => $damaged,
                    'missing' => $missing,
                ];
            })
            ->sortBy(fn ($row) => $row->asset?->name ?? '')
            ->values();
    }
}
