<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubcontractorProfile extends Model
{
    protected $fillable = [
        'user_id', 'company_name', 'contact_person', 'phone', 'email',
        'address', 'city', 'state', 'pincode', 'gst_number', 'pan_number',
        'bank_name', 'bank_account_no', 'bank_ifsc', 'specialization', 'notes',
        'is_verified', 'verified_at', 'verified_by', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'verified_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
