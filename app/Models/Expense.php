<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Expense extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['work_order_id', 'type', 'category', 'description', 'remark', 'amount', 'balance', 'expense_date', 'paid_by', 'approved_by'];

    protected function casts(): array
    {
        return ['expense_date' => 'date'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('bill')->singleFile();
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
