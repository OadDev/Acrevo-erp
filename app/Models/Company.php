<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name', 'legal_name', 'logo_path', 'email', 'phone', 'address',
        'city', 'state', 'pincode', 'gstin', 'pan', 'website',
        'currency', 'timezone', 'settings',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }
}
