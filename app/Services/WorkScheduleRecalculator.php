<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteWorkSchedule;
use Illuminate\Support\Carbon;

/**
 * Rebuilds every SiteWorkSchedule's revised_start_date/revised_end_date
 * for a site whenever any schedule for that site is added, edited, or
 * removed.
 *
 * Each work is one of two modes:
 *   - 'independent': its revised_start_date is whatever the admin set
 *     directly and is never touched here - two or more independent works
 *     can freely start on the same day with no relationship to each
 *     other, and editing one never moves another.
 *   - 'depends_on': starts the day after its dependency's effective end
 *     date, plus its own lag_days gap. "Effective" end is the actual_*
 *     date once the dependency is completed, otherwise its planned
 *     revised_end_date - so an early or late actual completion
 *     automatically reflows whatever depends on it.
 *
 * A schedule may only depend on a schedule with a smaller id (enforced in
 * SiteWorkScheduleController's validation), which makes the dependency
 * graph a DAG by construction - processing in ascending id order always
 * resolves a dependency before the row that needs it, in one pass, with
 * no cycle risk.
 *
 * original_* fields are never touched here - only set once, at creation.
 */
class WorkScheduleRecalculator
{
    public static function recalculate(Site $site): void
    {
        $schedules = $site->workSchedules()->orderBy('id')->get()->keyBy('id');

        foreach ($schedules as $schedule) {
            if ($schedule->schedule_mode === 'depends_on' && $schedule->depends_on_schedule_id && $schedules->has($schedule->depends_on_schedule_id)) {
                $dependency = $schedules->get($schedule->depends_on_schedule_id);
                $dependencyEnd = $dependency->actual_end_date ?? $dependency->revised_end_date;
                $start = $dependencyEnd->copy()->addDay()->addDays($schedule->lag_days);
            } else {
                $start = $schedule->revised_start_date;
            }

            $end = $start->copy()->addDays(max(0, $schedule->revised_duration_days - 1));

            if ($schedule->revised_start_date?->ne($start) || $schedule->revised_end_date?->ne($end)) {
                $schedule->forceFill([
                    'revised_start_date' => $start,
                    'revised_end_date' => $end,
                ])->save();
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
