<?php

namespace App\Models;

use App\Models\Concerns\HasCompanySequenceNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class DeliveryChallan extends Model
{
    use HasCompanySequenceNumber, LogsActivity, SoftDeletes;

    public const STATUSES = ['draft', 'delivered'];

    protected $sequencePrefix = 'DC';

    protected $sequenceColumn = 'challan_no';

    protected $fillable = [
        'company_id', 'client_id', 'challan_no', 'challan_date', 'delivery_time',
        'shipping_name', 'shipping_address', 'shipping_phone', 'shipping_email', 'shipping_tax_id',
        'terms_and_conditions', 'status',
        'received_by_name', 'received_by_comment', 'received_by_date',
        'delivered_by_name', 'delivered_by_comment', 'delivered_by_date',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'challan_date' => 'date',
            'received_by_date' => 'date',
            'delivered_by_date' => 'date',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryChallanItem::class)->orderBy('sort_order');
    }
}
