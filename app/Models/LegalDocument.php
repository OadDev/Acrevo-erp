<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class LegalDocument extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['work_order_id', 'client_id', 'type', 'title', 'reference_no', 'issued_date', 'expiry_date', 'status', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['issued_date' => 'date', 'expiry_date' => 'date'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
