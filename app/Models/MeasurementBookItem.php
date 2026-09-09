<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class MeasurementBookItem extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = ['measurement_book_id', 'item_description', 'unit', 'length', 'breadth', 'height', 'quantity', 'rate', 'amount'];

    public function measurementBook(): BelongsTo
    {
        return $this->belongsTo(MeasurementBook::class);
    }
}
