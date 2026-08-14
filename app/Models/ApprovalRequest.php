<?php

namespace App\Models;

use App\Models\Concerns\HasSequenceNumber;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class ApprovalRequest extends Model implements HasMedia
{
    use HasFactory, HasSequenceNumber, HasUuids, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $sequencePrefix = 'AR';

    protected $sequenceColumn = 'approval_no';

    public const DIRECTIONS = ['company_to_client', 'client_to_company'];

    public const STATUSES = ['pending', 'approved', 'rejected'];

    protected $fillable = [
        'approval_no', 'work_order_id', 'direction', 'title', 'description',
        'requested_by', 'requested_by_client_id', 'status', 'response_note',
        'responded_by', 'responded_at',
    ];

    protected function casts(): array
    {
        return ['responded_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('attachment')->singleFile();
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function requestedByClient(): BelongsTo
    {
        return $this->belongsTo(Client::class, 'requested_by_client_id');
    }

    public function respondedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responded_by');
    }
}
