<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetMovement extends Model
{
    public const TYPES = [
        'purchase', 'company_store', 'site_allocation', 'site_to_site_transfer',
        'site_return', 'repair_movement', 'other',
    ];

    public const STATUSES = ['pending', 'confirmed', 'cancelled'];

    // A superset of Asset::LOCATIONS - a movement can originate from a
    // supplier (purchase) or a repair vendor (repair movement), neither of
    // which an asset ever "rests" at as its own current_location.
    public const LOCATIONS = ['supplier', 'company_store', 'work_order', 'in_transit', 'repair', 'other'];

    protected $fillable = [
        'asset_id', 'type', 'from_location', 'from_work_order_id', 'to_location', 'to_work_order_id',
        'status', 'moved_at', 'confirmed_at', 'remarks', 'created_by', 'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'date',
            'confirmed_at' => 'datetime',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function fromWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'from_work_order_id');
    }

    public function toWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'to_work_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function locationLabel(string $location, ?WorkOrder $workOrder): string
    {
        return $location === 'work_order' && $workOrder
            ? $workOrder->work_order_no
            : ucwords(str_replace('_', ' ', $location));
    }
}
