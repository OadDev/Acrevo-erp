<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Ledger extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['work_order_id', 'entry_date', 'type', 'category', 'description', 'amount', 'balance', 'created_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('bill')->singleFile();
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
