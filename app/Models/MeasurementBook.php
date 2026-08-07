<?php

namespace App\Models;

use App\Models\Concerns\HasSequenceNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeasurementBook extends Model
{
    use HasSequenceNumber;

    protected $sequencePrefix = 'MB';

    protected $sequenceColumn = 'mb_no';

    protected $fillable = ['work_order_id', 'mb_no', 'description', 'date', 'recorded_by', 'status'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(MeasurementBookItem::class);
    }
}
