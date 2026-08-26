<?php

namespace App\Models;

use App\Models\Concerns\HasSequenceNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasSequenceNumber, SoftDeletes;

    protected $sequencePrefix = 'INV';

    protected $sequenceColumn = 'invoice_no';

    protected $fillable = ['invoice_no', 'work_order_id', 'client_id', 'amount', 'tax_amount', 'total_amount', 'due_date', 'status', 'issued_by'];

    protected function casts(): array
    {
        return ['due_date' => 'date'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function client(): BelongsTo
    {
        // withTrashed() so a removed client's invoices still show their name
        // instead of crashing on null.
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function paidAmount(): float
    {
        return (float) $this->payments->sum('amount');
    }

    public function balanceDue(): float
    {
        return max(0, (float) $this->total_amount - $this->paidAmount());
    }
}
