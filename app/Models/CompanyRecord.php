<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class CompanyRecord extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = ['type', 'name', 'number', 'issued_date', 'expiry_date', 'notes'];

    protected function casts(): array
    {
        return ['issued_date' => 'date', 'expiry_date' => 'date'];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files');
    }
}
