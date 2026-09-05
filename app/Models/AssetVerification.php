<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class AssetVerification extends Model implements HasMedia
{
    use InteractsWithMedia;

    public const RESULTS = ['verified_ok', 'damaged', 'not_found'];

    protected $fillable = [
        'asset_id', 'result', 'condition', 'remarks', 'work_order_id', 'verified_at', 'verified_by',
    ];

    protected function casts(): array
    {
        return ['verified_at' => 'date'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('proof');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
