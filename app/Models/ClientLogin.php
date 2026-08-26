<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientLogin extends Model
{
    protected $fillable = ['client_id', 'user_id'];

    public function client(): BelongsTo
    {
        // withTrashed() so a removed client's login record still resolves
        // instead of crashing on null.
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
