<?php

namespace App\Models;

use App\Models\Concerns\HasBillingTotals;
use App\Models\Concerns\HasCompanySequenceNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TaxInvoice extends Model
{
    use HasBillingTotals, HasCompanySequenceNumber, LogsActivity, SoftDeletes;

    public const STATUSES = ['draft', 'sent'];

    protected $sequencePrefix = 'TI';

    protected $sequenceColumn = 'tax_invoice_no';

    protected $fillable = [
        'company_id', 'client_id', 'tax_invoice_no', 'document_date',
        'delivery_note', 'payment_terms', 'supplier_ref', 'other_reference',
        'buyer_order_no', 'buyer_order_date', 'dispatch_doc_no', 'dispatch_through',
        'destination', 'terms_of_delivery', 'subtotal', 'tax_percent', 'tax_amount',
        'total_amount', 'status', 'notes', 'proforma_invoice_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'document_date' => 'date',
            'buyer_order_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
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
        return $this->hasMany(TaxInvoiceItem::class)->orderBy('sort_order');
    }

    public function proformaInvoice(): BelongsTo
    {
        return $this->belongsTo(ProformaInvoice::class);
    }
}
