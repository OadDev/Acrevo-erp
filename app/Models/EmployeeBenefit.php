<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBenefit extends Model
{
    protected $fillable = ['employee_id', 'type', 'details', 'amount', 'effective_date'];

    protected function casts(): array
    {
        return ['details' => 'array', 'effective_date' => 'date'];
    }

    public function employee(): BelongsTo
    {
        // withTrashed() so a permanently removed worker's historical
        // entries still show their name instead of crashing on null.
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
