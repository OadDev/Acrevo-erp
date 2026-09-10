<?php

namespace App\Models;

use App\Models\Concerns\HasSequenceNumber;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Client extends Model
{
    use HasFactory, HasSequenceNumber, HasUuids, LogsActivity, SoftDeletes;

    protected $sequencePrefix = 'CL';

    protected $sequenceColumn = 'client_code';

    protected $fillable = [
        'client_code', 'name', 'type', 'email', 'phone', 'alternate_phone',
        'address', 'city', 'state', 'pincode', 'gstin', 'source',
        'assigned_sales_user_id', 'notes', 'is_active', 'visible_sections',
        'work_order_discussion_access', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'visible_sections' => 'array',
            'work_order_discussion_access' => 'boolean',
        ];
    }

    /**
     * Null visible_sections means unrestricted (every section visible) -
     * the default so existing clients keep seeing what they already see
     * until an Admin deliberately restricts them.
     */
    public function canViewSection(string $key): bool
    {
        return $this->visible_sections === null || in_array($key, $this->visible_sections, true);
    }

    /**
     * Unlike canViewSection() above, this has no "unrestricted" bypass -
     * it defaults to false for every client, since it's not just read
     * visibility but the ability to send messages into a thread staff
     * also see. An Admin has to turn it on explicitly per client.
     */
    public function canAccessWorkOrderDiscussion(): bool
    {
        return $this->work_order_discussion_access;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function assignedSales(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_sales_user_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function workOrders(): HasMany
    {
        return $this->hasMany(WorkOrder::class);
    }

    public function sites(): HasMany
    {
        return $this->hasMany(Site::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function clientLogin(): HasOne
    {
        return $this->hasOne(ClientLogin::class);
    }
}
