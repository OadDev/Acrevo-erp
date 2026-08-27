<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DailyProgressReport extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'work_order_id', 'executive_team_id', 'date', 'completed_work',
        'pending_work', 'problems', 'materials_required', 'remarks', 'submitted_by',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function executiveTeam(): BelongsTo
    {
        return $this->belongsTo(ExecutiveTeam::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }
}
