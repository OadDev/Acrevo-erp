<?php

namespace App\Models;

use App\Models\Concerns\HasSequenceNumber;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Site extends Model
{
    use HasFactory, HasSequenceNumber, HasUuids;

    protected $sequencePrefix = 'ST';

    protected $sequenceColumn = 'site_no';

    public const STATUSES = ['active', 'completed'];

    protected $fillable = [
        'site_no', 'quotation_id', 'client_id', 'address', 'city', 'state', 'pincode',
        'site_contact_name', 'site_contact_phone', 'status', 'completed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function quotation(): BelongsTo
    {
        return $this->belongsTo(Quotation::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }
}
