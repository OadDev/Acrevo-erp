<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * The ledger of how much of an Asset sits where and in what state - the
 * source of truth behind Asset::quantity. A single Asset (e.g. "Steel
 * Sheets", quantity 20) can have several of these rows at once: 10
 * Available at Company Store, 7 Available at a work order, 3 In Transit
 * between them. Movements/Repairs/Verifications move quantity between
 * buckets via adjust() rather than editing Asset's own location/status.
 */
class AssetStock extends Model
{
    public const STATUSES = ['available', 'in_transit', 'missing', 'damaged', 'under_repair'];

    protected $fillable = ['asset_id', 'location', 'work_order_id', 'status', 'quantity'];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    /**
     * The quantity of $asset currently Available at a given location - the
     * ceiling for how much can be moved, repaired, or verified out of it.
     */
    public static function availableAt(Asset $asset, string $location, ?string $workOrderId): int
    {
        return static::quantityAt($asset, $location, $workOrderId, 'available');
    }

    public static function quantityAt(Asset $asset, string $location, ?string $workOrderId, string $status): int
    {
        return (int) $asset->stocks()
            ->where('location', $location)
            ->where('work_order_id', $workOrderId)
            ->where('status', $status)
            ->sum('quantity');
    }

    /**
     * Releases up to $quantity out of a bucket, never taking it below zero,
     * and returns how much was actually released. Movements/repairs created
     * before this ledger existed never reserved anything in the first
     * place (their in_transit/under_repair bucket is 0), so confirming,
     * cancelling, editing, or removing one of those must not blow up trying
     * to release more than was ever put there - it should release nothing
     * and leave it to the caller to decide what, if anything, to credit
     * elsewhere. A movement/repair created under this ledger always has
     * its full quantity reserved, so this is a no-op difference for those -
     * it releases the full amount, same as adjust() would have.
     */
    public static function release(Asset $asset, string $location, ?string $workOrderId, string $status, int $quantity): int
    {
        $toRelease = min($quantity, static::quantityAt($asset, $location, $workOrderId, $status));

        if ($toRelease > 0) {
            static::adjust($asset, $location, $workOrderId, $status, -$toRelease);
        }

        return $toRelease;
    }

    /**
     * Moves $quantity of $asset into the (location, work_order, status)
     * bucket (or out of it, for a negative $quantity), creating the bucket
     * on first use. Refuses to take a bucket negative - callers must
     * validate against availableAt() first so this is always a programming
     * error, not a user-facing one, when it fires.
     */
    public static function adjust(Asset $asset, string $location, ?string $workOrderId, string $status, int $quantity): void
    {
        if ($quantity === 0) {
            return;
        }

        $bucket = static::firstOrCreate([
            'asset_id' => $asset->id,
            'location' => $location,
            'work_order_id' => $workOrderId,
            'status' => $status,
        ], ['quantity' => 0]);

        $newQuantity = $bucket->quantity + $quantity;

        if ($newQuantity < 0) {
            throw new RuntimeException("Cannot take asset #{$asset->id}'s \"{$status}\" bucket at \"{$location}\" below zero.");
        }

        $bucket->update(['quantity' => $newQuantity]);

        $asset->update(['quantity' => $asset->stocks()->sum('quantity')]);
    }
}
