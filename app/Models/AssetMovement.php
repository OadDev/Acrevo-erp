<?php

namespace App\Models;

use App\Models\Concerns\LogsAssetAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class AssetMovement extends Model
{
    use LogsActivity, LogsAssetAudit;

    public const TYPES = [
        'purchase', 'company_store', 'site_allocation', 'site_to_site_transfer',
        'site_return', 'repair_movement', 'other',
    ];

    public const STATUSES = ['pending', 'confirmed', 'cancelled'];

    // Display labels for TYPES - kept separate from the stored value so the
    // wording can be revised without a data migration. Anything missing
    // here (there shouldn't be) falls back to a title-cased version of the
    // raw value in typeLabel()/labelForType().
    public const TYPE_LABELS = [
        'purchase' => 'Purchase',
        'company_store' => 'Company Store to Allocated WO',
        'site_allocation' => 'Site Allocated to Company Store',
        'site_to_site_transfer' => 'Site to Site Transfer',
        'site_return' => 'Return to Site',
        'repair_movement' => 'Repair Movement',
        'other' => 'Other',
    ];

    // A superset of Asset::LOCATIONS - a movement can originate from a
    // supplier (purchase) or a repair vendor (repair movement), neither of
    // which an asset ever "rests" at as its own current_location.
    public const LOCATIONS = ['supplier', 'company_store', 'work_order', 'in_transit', 'repair', 'other'];

    protected $fillable = [
        'asset_id', 'type', 'quantity', 'from_location', 'from_work_order_id', 'to_location', 'to_work_order_id',
        'status', 'moved_at', 'confirmed_at', 'remarks', 'created_by', 'confirmed_by',
    ];

    protected function casts(): array
    {
        return [
            'moved_at' => 'date',
            'confirmed_at' => 'datetime',
            'quantity' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('assets')
            ->logOnly(['asset_id', 'type', 'from_location', 'from_work_order_id', 'to_location', 'to_work_order_id', 'status', 'remarks'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
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

    public static function labelForType(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    public function typeLabel(): string
    {
        return self::labelForType($this->type);
    }
}
