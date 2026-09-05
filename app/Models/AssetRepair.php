<?php

namespace App\Models;

use App\Models\Concerns\LogsAssetAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssetRepair extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity, LogsAssetAudit;

    public const TYPES = ['mechanical', 'electrical', 'cosmetic', 'other'];

    public const STATUSES = ['reported', 'in_progress', 'completed', 'cancelled'];

    protected $fillable = [
        'asset_id', 'repair_type', 'issue_description', 'technician_vendor', 'is_warranty_repair',
        'cost', 'status', 'asset_status_before', 'work_order_id', 'reported_date', 'completed_date',
        'remarks', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_warranty_repair' => 'boolean',
            'cost' => 'decimal:2',
            'reported_date' => 'date',
            'completed_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('assets')
            ->logOnly(['asset_id', 'repair_type', 'issue_description', 'technician_vendor', 'cost', 'status', 'work_order_id', 'remarks'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
