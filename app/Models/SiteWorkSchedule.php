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

    public const MODES = ['independent', 'depends_on'];

    protected $fillable = [
        'site_id', 'sequence_order', 'work_name', 'work_details', 'executive_team_id', 'schedule_mode', 'depends_on_schedule_id', 'lag_days',
        'original_start_date', 'original_duration_days', 'original_end_date',
        'revised_start_date', 'revised_duration_days', 'revised_end_date',
        'actual_start_date', 'actual_end_date', 'actual_progress_percent',
        'status', 'delay_reason', 'created_by',
    ];

    protected function casts(): array
    {
        return [
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
                'work_name', 'work_details', 'executive_team_id', 'schedule_mode', 'depends_on_schedule_id', 'lag_days',
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

    public function dependsOn(): BelongsTo
    {
        return $this->belongsTo(self::class, 'depends_on_schedule_id');
    }

    public function executiveTeam(): BelongsTo
    {
        return $this->belongsTo(ExecutiveTeam::class);
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
     * Compares against the actual completion date once known, otherwise
     * the current planned (revised) end date - so recording an Actual End
     * Date immediately updates the variance shown, without needing to
     * separately touch duration or the dependency gap.
     *
     * This is the work's TOTAL variance - it doesn't distinguish how much
     * of it is this work's own doing versus inherited from a delayed
     * dependency. See previousWorkDelayDays()/ownDelayDays() for that split.
     */
    public function varianceDays(): int
    {
        $comparisonEnd = $this->actual_end_date ?? $this->revised_end_date;

        return $this->original_end_date->diffInDays($comparisonEnd, false);
    }

    /**
     * How many days the work actually took, from its real start to its
     * real completion - independent of the original/revised duration
     * fields, which are never touched by recording these dates. Null
     * while either date is still missing (e.g. work not yet started, or
     * still in progress with no completion date recorded yet).
     */
    public function actualDurationDays(): ?int
    {
        if (! $this->actual_start_date || ! $this->actual_end_date) {
            return null;
        }

        return $this->actual_start_date->diffInDays($this->actual_end_date) + 1;
    }

    /**
     * Positive = started that many days later than currently planned,
     * negative = started early, 0 = started exactly on plan, null = not
     * started yet. Compares against revised_start_date (this work's own,
     * already dependency-adjusted plan), not the original start date, so
     * this reflects whether the work itself started on time against what
     * it was actually supposed to start on - not whatever the schedule
     * looked like before an earlier work's delay shifted it.
     */
    public function startVarianceDays(): ?int
    {
        if (! $this->actual_start_date) {
            return null;
        }

        return $this->revised_start_date->diffInDays($this->actual_start_date, false);
    }

    /**
     * The portion of this work's total variance that it inherited from its
     * dependency running late (or early) - i.e. the dependency's own total
     * variance, carried forward. Zero for an independent work, since
     * nothing upstream can push its start date around.
     */
    public function previousWorkDelayDays(): int
    {
        if ($this->schedule_mode !== 'depends_on' || ! $this->dependsOn) {
            return 0;
        }

        return $this->dependsOn->varianceDays();
    }

    /**
     * This work's own contribution to its total variance, with whatever it
     * inherited from a delayed dependency subtracted out - so a work that's
     * exactly on its own (already-shifted) schedule shows 0 here even if
     * the site overall is running late because of an earlier work.
     */
    public function ownDelayDays(): int
    {
        return $this->varianceDays() - $this->previousWorkDelayDays();
    }

    /**
     * True only when this work's OWN execution has overrun its own
     * (already dependency-adjusted) schedule - never true just because an
     * earlier, unrelated work pushed this one's start date out.
     */
    public function isDelayed(): bool
    {
        if ($this->actual_end_date) {
            return $this->actual_end_date->gt($this->revised_end_date);
        }

        return now()->startOfDay()->gt($this->revised_end_date);
    }
}
