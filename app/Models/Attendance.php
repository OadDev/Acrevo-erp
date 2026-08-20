<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendance extends Model
{
    protected $fillable = ['employee_id', 'work_order_id', 'date', 'status', 'work_details', 'check_in', 'check_out', 'break_minutes', 'hours_worked', 'salary', 'advance', 'marked_by'];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'salary' => 'decimal:2',
            'advance' => 'decimal:2',
        ];
    }

    public function employee(): BelongsTo
    {
        // withTrashed() so a permanently removed worker's historical
        // entries still show their name instead of crashing on null.
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }
}
