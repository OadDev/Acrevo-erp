<?php

namespace App\Models;

use App\Models\Concerns\HasSequenceNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Asset extends Model implements HasMedia
{
    use HasFactory, HasSequenceNumber, InteractsWithMedia, SoftDeletes;

    protected $sequencePrefix = 'AST';

    protected $sequenceColumn = 'asset_code';

    public const STATUSES = [
        'available', 'in_use', 'under_repair', 'repaired', 'damaged',
        'missing', 'ready_for_return', 'returned', 'retired',
    ];

    public const LOCATIONS = ['company_store', 'work_order', 'in_transit', 'other'];

    protected $fillable = [
        'asset_code', 'name', 'category', 'brand', 'model', 'serial_number', 'status', 'condition',
        'current_location', 'current_work_order_id',
        'purchase_date', 'purchase_cost', 'supplier', 'invoice_number',
        'warranty_start', 'warranty_end', 'warranty_provider', 'warranty_card_details',
        'remarks', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'purchase_date' => 'date',
            'warranty_start' => 'date',
            'warranty_end' => 'date',
            'purchase_cost' => 'decimal:2',
        ];
    }

    public function registerMediaCollections(): void
    {
        // Photos/working video, purchase invoice, warranty documents, manuals.
        $this->addMediaCollection('attachments');
        // Asset-related reports (image/pdf/video), kept separate from the
        // purchase/warranty attachments above so the two upload sections on
        // the create/edit form don't mix unrelated files together.
        $this->addMediaCollection('reports');
    }

    public function currentWorkOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class, 'current_work_order_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(AssetStatusLog::class)->latest();
    }

    public function changeRequests(): HasMany
    {
        return $this->hasMany(AssetChangeRequest::class)->latest();
    }

    public function movements(): HasMany
    {
        return $this->hasMany(AssetMovement::class)->orderBy('moved_at')->orderBy('id');
    }

    public function repairs(): HasMany
    {
        return $this->hasMany(AssetRepair::class)->orderBy('reported_date')->orderBy('id');
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(AssetVerification::class)->latest('verified_at')->latest('id');
    }

    public function warrantyStatus(): ?string
    {
        if (! $this->warranty_end) {
            return null;
        }

        if ($this->warranty_end->isPast()) {
            return 'expired';
        }

        if ($this->warranty_end->diffInDays(now()) <= 30) {
            return 'expiring_soon';
        }

        return 'active';
    }
}
