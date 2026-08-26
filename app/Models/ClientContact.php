<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClientContact extends Model
{
    protected $fillable = ['client_id', 'name', 'designation', 'phone', 'email', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        // withTrashed() so a removed client's contacts still resolve instead
        // of crashing on null.
        return $this->belongsTo(Client::class)->withTrashed();
    }
}
