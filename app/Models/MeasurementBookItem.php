<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeasurementBookItem extends Model
{
    protected $fillable = ['measurement_book_id', 'item_description', 'unit', 'length', 'breadth', 'height', 'quantity', 'rate', 'amount'];

    public function measurementBook(): BelongsTo
    {
        return $this->belongsTo(MeasurementBook::class);
    }
}
