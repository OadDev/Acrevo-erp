<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrder;

class WorkOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('work_orders.view')
            || $user->can('assigned_work.view')
            || $user->can('client_portal.access');
    }

    public function view(User $user, WorkOrder $workOrder): bool
    {
        if ($user->can('work_orders.view')) {
            return true;
        }

        if ($user->can('assigned_work.view')) {
            return $workOrder->executiveTeams()
                ->whereNull('unassigned_at')
                ->whereHas('executiveTeam.members', fn ($q) => $q->where('employee_id', $user->employee?->id))
                ->exists()
                || $workOrder->executiveTeams()->whereNull('unassigned_at')
                    ->whereHas('executiveTeam', fn ($q) => $q->where('team_leader_id', $user->id))
                    ->exists();
        }

        if ($user->can('client_portal.access')) {
            return $workOrder->client_id === $user->client()?->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('work_orders.create');
    }

    public function update(User $user, WorkOrder $workOrder): bool
    {
        return $user->can('work_orders.edit');
    }

    public function cancel(User $user, WorkOrder $workOrder): bool
    {
        return $user->can('work_orders.cancel');
    }
}
