<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LabourEntry extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = ['work_order_id', 'employee_id', 'labour_type', 'count', 'wage_rate', 'hours', 'total_time_to_finish', 'remark', 'amount', 'entry_date', 'added_by'];

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function employee(): BelongsTo
    {
        // withTrashed() so a permanently removed worker's historical
        // entries still show their name instead of crashing on null.
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'added_by');
    }
}
