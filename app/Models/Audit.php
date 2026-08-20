<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Audit extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['type', 'work_order_id', 'title', 'auditor_id', 'audit_date', 'findings', 'status', 'report_path'];

    protected function casts(): array
    {
        return ['audit_date' => 'date'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function auditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }
}
