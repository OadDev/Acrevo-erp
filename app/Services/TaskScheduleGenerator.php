<?php

namespace App\Services;

use App\Models\Task;
use App\Models\TaskSchedule;
use App\Models\User;
use Carbon\Carbon;

class TaskScheduleGenerator
{
    /**
     * Lazily materializes today's due Calendar Task instances for a user,
     * since this app has no cron/scheduler running on the host - instances
     * are generated the moment the assignee (or a matching role holder)
     * next opens their Task list, one per schedule per period at most.
     */
    public function generateForUser(User $user): void
    {
        $today = Carbon::today();
        $roleNames = $user->roles->pluck('name');

        TaskSchedule::query()
            ->where('is_active', true)
            ->where(function ($query) use ($user, $roleNames) {
                $query->where('assigned_to_user_id', $user->id)
                    ->orWhereIn('assignee_role', $roleNames);
            })
            ->get()
            ->each(function (TaskSchedule $schedule) use ($user, $today) {
                $due = $schedule->dueDateFor($today);

                if (! $due) {
                    return;
                }

                $periodKey = $schedule->periodKeyFor($today);

                $exists = Task::where('task_schedule_id', $schedule->id)
                    ->where('assigned_to', $user->id)
                    ->where('period_key', $periodKey)
                    ->exists();

                if ($exists) {
                    return;
                }

                Task::create([
                    'task_schedule_id' => $schedule->id,
                    'assigned_by' => $schedule->created_by,
                    'assigned_to' => $user->id,
                    'verifier_id' => $schedule->verifier_user_id,
                    'title' => $schedule->title,
                    'description' => $schedule->description,
                    'due_date' => $due->toDateString(),
                    'status' => 'pending',
                    'period_key' => $periodKey,
                ]);
            });
    }
}
