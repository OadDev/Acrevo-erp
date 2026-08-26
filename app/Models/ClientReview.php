<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientReview extends Model
{
    protected $fillable = ['work_order_id', 'client_id', 'rating', 'comments', 'reviewed_at'];

    protected function casts(): array
    {
        return ['reviewed_at' => 'datetime'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function client(): BelongsTo
    {
        // withTrashed() so a removed client's past reviews still show their
        // name instead of crashing on null.
        return $this->belongsTo(Client::class)->withTrashed();
    }
}
