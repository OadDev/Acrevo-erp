<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Task extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'task_schedule_id', 'assigned_by', 'assigned_to', 'verifier_id',
        'title', 'description', 'due_date', 'status',
        'completed_at', 'completion_notes', 'delay_reason', 'delay_reported_at',
        'verified_at', 'verified_by', 'retask_note', 'parent_task_id', 'period_key',
    ];

    protected function casts(): array
    {
        return [
            'due_date' => 'date',
            'completed_at' => 'datetime',
            'delay_reported_at' => 'datetime',
            'verified_at' => 'datetime',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('proof');
        $this->addMediaCollection('attachments');
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(TaskSchedule::class, 'task_schedule_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function parentTask(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'parent_task_id');
    }

    public function retasks(): HasMany
    {
        return $this->hasMany(Task::class, 'parent_task_id');
    }

    public function isOverdue(): bool
    {
        return $this->status === 'pending' && $this->due_date->isPast();
    }
}
