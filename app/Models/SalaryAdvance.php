<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SalaryAdvance extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = ['employee_id', 'amount', 'reason', 'requested_at', 'approved_by', 'status', 'repayment_status'];

    protected function casts(): array
    {
        return ['requested_at' => 'date'];
    }

    public function employee(): BelongsTo
    {
        // withTrashed() so a permanently removed worker's historical
        // entries still show their name instead of crashing on null.
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
