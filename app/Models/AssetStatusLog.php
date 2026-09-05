<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssetStatusLog extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'asset_id', 'previous_status', 'new_status', 'updated_by', 'role', 'work_order_id', 'reason',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('proof');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }
}
