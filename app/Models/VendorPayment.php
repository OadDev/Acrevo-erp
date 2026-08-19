<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorPayment extends Model
{
    protected $fillable = ['vendor_name', 'user_id', 'work_order_id', 'amount', 'payment_date', 'mode', 'reference_no', 'category', 'remark', 'paid_by'];

    protected function casts(): array
    {
        return ['payment_date' => 'date'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function subContractor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }
}
