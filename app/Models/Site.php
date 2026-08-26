<?php

namespace App\Models;

use App\Models\Concerns\HasSequenceNumber;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Site extends Model implements HasMedia
{
    use HasFactory, HasSequenceNumber, HasUuids, InteractsWithMedia;

    public const DOCUMENT_CATEGORIES = ['kyc', 'land_document', 'gov_record', 'other'];

    protected $sequencePrefix = 'ST';

    protected $sequenceColumn = 'site_no';

    public const STATUSES = ['active', 'completed'];

    protected $fillable = [
        'site_no', 'quotation_id', 'client_id', 'address', 'city', 'state', 'pincode',
        'construction_site_location', 'client_living_location',
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
        // withTrashed() so a removed client's existing sites still show their
        // name instead of crashing on null.
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function subContractors(): HasMany
    {
        return $this->hasMany(SiteSubContractor::class);
    }

    public function registerMediaCollections(): void
    {
        foreach (self::DOCUMENT_CATEGORIES as $category) {
            $this->addMediaCollection($category);
        }
    }
}
