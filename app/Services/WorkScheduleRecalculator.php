<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteWorkSchedule;
use Illuminate\Support\Carbon;

/**
 * Rebuilds every SiteWorkSchedule's revised_start_date/revised_end_date
 * for a site, in sequence_order, whenever any schedule for that site is
 * added, edited, reordered, or removed.
 *
 * The chain rule (per site, walking sequence_order ascending):
 *   - A sequential item (is_parallel = false) starts the day after the
 *     running end date (the latest effective end date reached by any
 *     item so far), plus its own lag_days gap. It becomes the new
 *     "anchor" for any parallel items that follow.
 *   - A parallel item (is_parallel = true) starts alongside the current
 *     anchor's effective start date, plus its own lag_days.
 *   - "Effective" end/start date is the actual_* date once the work is
 *     marked completed/started, otherwise the planned revised_* date -
 *     so an early or late actual completion automatically reflows
 *     everything scheduled after it.
 *
 * original_* fields are never touched here - only set once, at creation.
 */
class WorkScheduleRecalculator
{
    public static function recalculate(Site $site): void
    {
        $schedules = $site->workSchedules()->orderBy('sequence_order')->get();

        if ($schedules->isEmpty()) {
            return;
        }

        $runningEnd = null;
        $anchorStart = null;

        foreach ($schedules as $schedule) {
            if ($schedule->is_parallel && $anchorStart !== null) {
                $start = $anchorStart->copy()->addDays($schedule->lag_days);
            } else {
                $start = $runningEnd === null
                    ? $schedule->revised_start_date ?? $schedule->original_start_date
                    : $runningEnd->copy()->addDay()->addDays($schedule->lag_days);
                $anchorStart = $start;
            }

            $end = $start->copy()->addDays(max(0, $schedule->revised_duration_days - 1));

            if ($schedule->revised_start_date?->ne($start) || $schedule->revised_end_date?->ne($end)) {
                $schedule->forceFill([
                    'revised_start_date' => $start,
                    'revised_end_date' => $end,
                ])->save();
            }

            $effectiveEnd = $schedule->actual_end_date ?? $end;
            $runningEnd = $runningEnd === null ? $effectiveEnd : $runningEnd->max($effectiveEnd);

            $effectiveAnchorStart = $schedule->actual_start_date ?? $start;
            if (! $schedule->is_parallel) {
                $anchorStart = $effectiveAnchorStart;
            }
        }
    }

    /**
     * The site's Day 1 - the earliest original_start_date among its
     * schedules - used to render "Day N" alongside calendar dates.
     */
    public static function projectStartDate(Site $site): ?Carbon
    {
        return $site->workSchedules->min('original_start_date');
    }

    public static function projectedCompletionDate(Site $site): ?Carbon
    {
        return $site->workSchedules->max('revised_end_date');
    }
}
