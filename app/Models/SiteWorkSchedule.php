<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class SiteWorkSchedule extends Model
{
    use LogsActivity;

    public const STATUSES = ['not_started', 'in_progress', 'completed'];

    protected $fillable = [
        'site_id', 'sequence_order', 'work_name', 'work_details', 'is_parallel', 'lag_days',
        'original_start_date', 'original_duration_days', 'original_end_date',
        'revised_start_date', 'revised_duration_days', 'revised_end_date',
        'actual_start_date', 'actual_end_date', 'actual_progress_percent',
        'status', 'delay_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_parallel' => 'boolean',
            'sequence_order' => 'integer',
            'lag_days' => 'integer',
            'original_start_date' => 'date',
            'original_duration_days' => 'integer',
            'original_end_date' => 'date',
            'revised_start_date' => 'date',
            'revised_duration_days' => 'integer',
            'revised_end_date' => 'date',
            'actual_start_date' => 'date',
            'actual_end_date' => 'date',
            'actual_progress_percent' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('work_schedules')
            ->logOnly([
                'work_name', 'work_details', 'is_parallel', 'lag_days',
                'revised_start_date', 'revised_duration_days', 'revised_end_date',
                'actual_start_date', 'actual_end_date', 'actual_progress_percent',
                'status', 'delay_reason',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Effective end date used by the recalculator when chaining the next
     * work: the actual completion date once known, otherwise the planned
     * (revised) end date.
     */
    public function effectiveEndDate(): \Illuminate\Support\Carbon
    {
        return $this->actual_end_date ?? $this->revised_end_date;
    }

    public function effectiveStartDate(): \Illuminate\Support\Carbon
    {
        return $this->actual_start_date ?? $this->revised_start_date;
    }

    /**
     * Day range relative to the site's own Day 1 (its earliest schedule's
     * original_start_date), so the UI can show "Day 11-13" alongside dates.
     */
    public function dayRange(): array
    {
        $day1 = $this->site->workSchedules->min('original_start_date') ?? $this->revised_start_date;

        $startDay = $day1->diffInDays($this->revised_start_date) + 1;
        $endDay = $day1->diffInDays($this->revised_end_date) + 1;

        return [$startDay, $endDay];
    }

    /**
     * Positive = extended/delayed by N days versus the original plan,
     * negative = finishing that many days early, 0 = on the original plan.
     */
    public function varianceDays(): int
    {
        return $this->original_end_date->diffInDays($this->revised_end_date, false);
    }

    public function isDelayed(): bool
    {
        if ($this->status === 'completed') {
            return $this->actual_end_date && $this->actual_end_date->gt($this->original_end_date);
        }

        return now()->startOfDay()->gt($this->revised_end_date);
    }
}
