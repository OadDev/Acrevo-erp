<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class DailyChecklistItem extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['daily_checklist_id', 'description', 'is_done', 'done_at', 'done_by', 'sort_order'];

    protected function casts(): array
    {
        return ['is_done' => 'boolean', 'done_at' => 'datetime'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('proof')->singleFile();
    }

    public function dailyChecklist(): BelongsTo
    {
        return $this->belongsTo(DailyChecklist::class);
    }

    public function doneBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'done_by');
    }
}
