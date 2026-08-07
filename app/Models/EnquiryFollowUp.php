<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnquiryFollowUp extends Model
{
    protected $fillable = ['enquiry_id', 'user_id', 'note', 'next_follow_up_date', 'status'];

    protected function casts(): array
    {
        return ['next_follow_up_date' => 'date'];
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
