<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class QcInspection extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = ['work_order_id', 'inspection_type', 'inspected_by', 'inspection_date', 'checklist', 'status', 'remarks', 'signature_path'];

    protected function casts(): array
    {
        return ['inspection_date' => 'date', 'checklist' => 'array'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('evidence');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function inspectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }
}
