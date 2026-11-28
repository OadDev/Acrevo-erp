<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class TicketComment extends Model implements HasMedia
{
    use InteractsWithMedia, LogsActivity;

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = ['ticket_id', 'user_id', 'comment', 'is_internal', 'edited_at'];

    protected function casts(): array
    {
        return ['is_internal' => 'boolean', 'edited_at' => 'datetime'];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isEdited(): bool
    {
        return $this->edited_at !== null;
    }
}
