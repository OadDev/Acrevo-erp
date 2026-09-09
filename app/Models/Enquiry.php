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

class Enquiry extends Model
{
    use HasFactory, HasSequenceNumber, HasUuids, LogsActivity, SoftDeletes;

    protected $sequencePrefix = 'ENQ';

    protected $sequenceColumn = 'enquiry_no';

    protected $fillable = [
        'enquiry_no', 'client_id', 'contact_name', 'contact_phone', 'contact_email',
        'address', 'city', 'source', 'service_type', 'description', 'status',
        'assigned_to', 'follow_up_date', 'lost_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return ['follow_up_date' => 'date'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(EnquiryFollowUp::class);
    }

    public function siteVisits(): HasMany
    {
        return $this->hasMany(SiteVisit::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
