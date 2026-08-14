<?php

namespace App\Models;

use App\Events\WorkOrderStatusChanged;
use App\Models\Concerns\HasSequenceNumber;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class WorkOrder extends Model implements HasMedia
{
    use HasFactory, HasSequenceNumber, HasUuids, InteractsWithMedia, LogsActivity, SoftDeletes;

    protected $sequencePrefix = 'WO';

    protected $sequenceColumn = 'work_order_no';

    /**
     * The centralized workflow status pipeline. Every department reads and
     * writes this single field to move a Work Order through the business flow.
     */
    public const STATUSES = [
        'pending_hr_assignment',
        'team_assigned',
        'in_progress',
        'qc_pending',
        'qc_passed',
        'qc_failed',
        'client_review',
        'ticket_raised',
        'rework_in_progress',
        'final_qc',
        'completed',
        'cancelled',
    ];

    /**
     * How the work is actually carried out, chosen at creation time:
     * an in-house team, a sub-contractor, or the client executing it
     * themselves (with optional chargeable company support).
     */
    public const EXECUTION_WAYS = [
        'way_1' => 'Way 1 — In-house Executive Team',
        'way_2' => 'Way 2 — Sub-Contractor',
        'way_3' => 'Way 3 — Client Self-Execution',
    ];

    protected $fillable = [
        'work_order_no', 'quotation_id', 'site_id', 'enquiry_id', 'client_id', 'parent_work_order_id',
        'type', 'title', 'scope', 'execution_way', 'priority', 'start_date', 'deadline',
        'budget_amount', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'deadline' => 'date',
            'budget_amount' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'status', 'priority', 'deadline'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('before_images');
        $this->addMediaCollection('during_images');
        $this->addMediaCollection('completion_images');
        $this->addMediaCollection('videos');
        $this->addMediaCollection('documents');
    }

    /**
     * Move the work order to a new workflow status and record the transition,
     * so every department can see how and when the work order progressed.
     */
    public function transitionTo(string $status, ?string $remarks = null): void
    {
        $from = $this->status;

        $this->update(['status' => $status]);

        $this->statusLogs()->create([
            'from_status' => $from,
            'to_status' => $status,
            'changed_by' => Auth::id(),
            'remarks' => $remarks,
            'changed_at' => now(),
        ]);

        event(new WorkOrderStatusChanged($this, $from));
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'parent_work_order_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(WorkOrder::class, 'parent_work_order_id');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(WorkOrderStatusLog::class)->orderByDesc('changed_at');
    }

    public function executiveTeams(): HasMany
    {
        return $this->hasMany(WorkOrderExecutiveTeam::class);
    }

    public function dailyChecklists(): HasMany
    {
        return $this->hasMany(DailyChecklist::class);
    }

    public function dailyProgressReports(): HasMany
    {
        return $this->hasMany(DailyProgressReport::class);
    }

    public function materialEntries(): HasMany
    {
        return $this->hasMany(MaterialEntry::class);
    }

    public function labourEntries(): HasMany
    {
        return $this->hasMany(LabourEntry::class);
    }

    public function measurementBooks(): HasMany
    {
        return $this->hasMany(MeasurementBook::class);
    }

    public function ledgers(): HasMany
    {
        return $this->hasMany(Ledger::class);
    }

    public function qcInspections(): HasMany
    {
        return $this->hasMany(QcInspection::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function approvalRequests(): HasMany
    {
        return $this->hasMany(ApprovalRequest::class)->latest();
    }

    public function clientReviews(): HasMany
    {
        return $this->hasMany(ClientReview::class);
    }

    public function completionCertificates(): HasMany
    {
        return $this->hasMany(CompletionCertificate::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }
}
