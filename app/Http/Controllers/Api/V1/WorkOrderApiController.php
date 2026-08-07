<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\WorkOrderResource;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkOrderApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $workOrders = WorkOrder::query()
            ->with('client')
            ->when(! $request->user()->can('work_orders.view'), function ($query) use ($request) {
                $employeeId = $request->user()->employee?->id;

                $query->whereHas('executiveTeams', function ($q) use ($employeeId, $request) {
                    $q->whereNull('unassigned_at')->whereHas('executiveTeam', function ($q2) use ($employeeId, $request) {
                        $q2->where('team_leader_id', $request->user()->id)
                            ->when($employeeId, fn ($q3) => $q3->orWhereHas('members', fn ($q4) => $q4->where('employee_id', $employeeId)));
                    });
                });
            })
            ->latest()
            ->paginate(20);

        return WorkOrderResource::collection($workOrders);
    }

    public function show(WorkOrder $workOrder): WorkOrderResource
    {
        $this->authorize('view', $workOrder);

        return new WorkOrderResource($workOrder->load('client'));
    }
}
