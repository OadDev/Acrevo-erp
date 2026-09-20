<?php

namespace App\Models;

use App\Models\Concerns\LogsAssetAudit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class EquipmentRequest extends Model
{
    use LogsActivity, LogsAssetAudit;

    // Requested -> Approved/Rejected -> (if Approved) Purchase Required or
    // Available -> Dispatched -> Received -> Completed, with Cancelled
    // reachable from any state before Dispatched.
    public const STATUSES = [
        'requested', 'approved', 'rejected', 'purchase_required',
        'available', 'dispatched', 'received', 'completed', 'cancelled',
    ];

    protected $fillable = [
        'work_order_id', 'requested_by', 'item_name', 'category', 'quantity', 'reason',
        'status', 'needs_purchase', 'asset_id', 'required_by_date',
        'approved_by', 'approved_at', 'rejection_reason', 'remarks',
    ];

    protected function casts(): array
    {
        return [
            'needs_purchase' => 'boolean',
            'required_by_date' => 'date',
            'approved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('assets')
            ->logOnly(['work_order_id', 'item_name', 'category', 'quantity', 'status', 'needs_purchase', 'asset_id', 'rejection_reason', 'remarks'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
