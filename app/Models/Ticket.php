<?php

namespace App\Models;

use App\Models\Concerns\HasSequenceNumber;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Ticket extends Model implements HasMedia
{
    use HasFactory, HasSequenceNumber, HasUuids, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $sequencePrefix = 'TKT';

    protected $sequenceColumn = 'ticket_no';

    protected $fillable = [
        'ticket_no', 'work_order_id', 'type', 'priority', 'title', 'description',
        'raised_by_type', 'raised_by', 'raised_by_client_id', 'department_id',
        'assigned_to', 'status', 'due_date', 'resolved_at', 'closed_at',
    ];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'resolved_at' => 'datetime', 'closed_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachments');
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function raisedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'raised_by');
    }

    public function raisedByClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'raised_by_client_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at');
    }
}
