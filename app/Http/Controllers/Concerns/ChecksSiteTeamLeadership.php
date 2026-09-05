<?php

namespace App\Http\Controllers\Concerns;

use App\Models\User;
use App\Models\WorkOrder;

/**
 * Shared by every Equipment & Asset Management controller that scopes an
 * Executive Team Leader's access to "assets/movements at a site they lead" -
 * mirrors WorkOrderPolicy's own team-leader check.
 */
trait ChecksSiteTeamLeadership
{
    protected function isTeamLeaderOfWorkOrder(?string $workOrderId, User $user): bool
    {
        return $workOrderId && WorkOrder::where('id', $workOrderId)
            ->whereHas('executiveTeams', fn ($q) => $q->whereNull('unassigned_at')
                ->whereHas('executiveTeam', fn ($q2) => $q2->where('team_leader_id', $user->id)))
            ->exists();
    }
}
