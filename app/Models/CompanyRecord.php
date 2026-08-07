<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyRecord extends Model
{
    protected $fillable = ['type', 'name', 'number', 'issued_date', 'expiry_date', 'notes'];

    protected function casts(): array
    {
        return ['issued_date' => 'date', 'expiry_date' => 'date'];
    }
}
