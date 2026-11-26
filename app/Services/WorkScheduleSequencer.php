<?php

namespace App\Services;

use App\Models\Site;
use App\Models\SiteWorkSchedule;
use Illuminate\Support\Facades\DB;

/**
 * Lets a site's work schedule be reshuffled - inserting a new work at any
 * position, or moving an existing one to a different position - without
 * ever deleting or recreating a row, so progress, status, and actual
 * dates on every other work survive untouched.
 *
 * sequence_order (not id) is the single source of truth for both display
 * order and dependency-graph ordering: a work may only depend_on another
 * work whose sequence_order is smaller. renumber() is the one place that
 * reassigns sequence_order, and it always re-validates the whole site's
 * dependency graph afterwards, so a move that would leave some work
 * depending on something now scheduled after it is rejected outright
 * rather than silently producing nonsense dates.
 */
class WorkScheduleSequencer
{
    /**
     * Reassigns sequence_order (1..N, matching array position) for every
     * one of the site's schedules to match $orderedIds, which must be a
     * permutation of every schedule id currently on the site - including
     * a row the caller just created, when inserting a new work.
     */
    public static function renumber(Site $site, array $orderedIds): void
    {
        $schedules = $site->workSchedules()->get()->keyBy('id');

        $expected = $schedules->keys()->sort()->values()->all();
        $given = collect($orderedIds)->sort()->values()->all();

        abort_unless($expected === $given, 422, 'Reorder list must contain every work on this site exactly once.');

        DB::transaction(function () use ($orderedIds, $schedules) {
            foreach (array_values($orderedIds) as $index => $id) {
                $schedules->get($id)->forceFill(['sequence_order' => $index + 1])->save();
            }

            foreach ($schedules as $schedule) {
                if ($schedule->schedule_mode !== 'depends_on' || ! $schedule->depends_on_schedule_id) {
                    continue;
                }

                $dependency = $schedules->get($schedule->depends_on_schedule_id);

                abort_if(
                    ! $dependency || $dependency->sequence_order >= $schedule->sequence_order,
                    422,
                    "Can't place \"{$schedule->work_name}\" there - it depends on \"".($dependency->work_name ?? 'a removed work')."\", which would then come after it. Change that dependency first."
                );
            }
        });
    }

    /**
     * After inserting $inserted between a predecessor and a successor: if
     * the successor depended directly on that same predecessor, re-point
     * it to depend on $inserted instead - so the chain flows through the
     * newly inserted work's own duration, without the admin having to
     * manually re-wire every work that comes after it. Left alone when the
     * successor depends on something else, or there is no predecessor.
     */
    public static function rewireSuccessorAfterInsert(Site $site, SiteWorkSchedule $inserted): void
    {
        $schedules = $site->workSchedules()->get()->keyBy('sequence_order');

        $predecessor = $schedules->get($inserted->sequence_order - 1);
        $successor = $schedules->get($inserted->sequence_order + 1);

        if (! $successor || ! $predecessor || $successor->schedule_mode !== 'depends_on') {
            return;
        }

        if ($successor->depends_on_schedule_id === $predecessor->id) {
            $successor->forceFill(['depends_on_schedule_id' => $inserted->id])->save();
        }
    }

    /**
     * Builds the full ordered id list for inserting $movingId (a brand new
     * row, or an existing one being relocated) immediately before
     * $beforeId, or at the end when $beforeId is null.
     */
    public static function orderedIdsWithMoveTo(Site $site, int $movingId, ?int $beforeId): array
    {
        $orderedIds = $site->workSchedules()->orderBy('sequence_order')->pluck('id')->all();
        $orderedIds = array_values(array_filter($orderedIds, fn ($id) => $id !== $movingId));

        $insertIndex = $beforeId !== null ? array_search($beforeId, $orderedIds, true) : count($orderedIds);
        abort_if($beforeId !== null && $insertIndex === false, 422, 'That work is no longer on this site.');

        array_splice($orderedIds, $insertIndex, 0, [$movingId]);

        return $orderedIds;
    }
}
