<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Company extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

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
