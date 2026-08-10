<?php

namespace App\Models;

use App\Models\Concerns\HasSequenceNumber;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Quotation extends Model
{
    use HasFactory, HasSequenceNumber, HasUuids, LogsActivity, SoftDeletes;

    protected $sequencePrefix = 'QT';

    protected $sequenceColumn = 'quotation_no';

    protected $fillable = [
        'quotation_no', 'enquiry_id', 'client_id', 'version', 'parent_quotation_id',
        'subtotal', 'discount_type', 'discount_value', 'tax_percent', 'tax_amount',
        'total_amount', 'terms', 'status', 'valid_until', 'approved_at', 'approved_by',
        'rejected_reason', 'pdf_path', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'valid_until' => 'date',
            'approved_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_value' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Quotation::class, 'parent_quotation_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(Quotation::class, 'parent_quotation_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function site(): HasOne
    {
        return $this->hasOne(Site::class);
    }

    public function recalculateTotals(): void
    {
        $subtotal = $this->items()->sum('total');
        $discount = $this->discount_type === 'percent'
            ? $subtotal * ($this->discount_value / 100)
            : $this->discount_value;
        $taxable = max($subtotal - $discount, 0);
        $tax = $taxable * ($this->tax_percent / 100);

        $this->update([
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'total_amount' => $taxable + $tax,
        ]);
    }
}
