<?php

namespace App\Http\Controllers;

use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyWorkOrderController extends Controller
{
    public function index(Request $request): View
    {
        $workOrders = $this->assignedWorkOrders($request)
            ->whereNotIn('work_orders.status', ['completed', 'cancelled'])
            ->paginate(15);

        return view('my-work-orders.index', compact('workOrders'));
    }

    public function show(WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('view', $workOrder);

        return redirect()->route('work-orders.show', $workOrder);
    }

    private function assignedWorkOrders(Request $request)
    {
        $user = $request->user();
        $employeeId = $user->employee?->id;

        return WorkOrder::query()
            ->with('client')
            ->whereHas('executiveTeams', function ($q) use ($employeeId, $user) {
                $q->whereNull('unassigned_at')->whereHas('executiveTeam', function ($q2) use ($employeeId, $user) {
                    $q2->where('team_leader_id', $user->id)
                        ->when($employeeId, fn ($q3) => $q3->orWhereHas('members', fn ($q4) => $q4->where('employee_id', $employeeId)));
                });
            })
            ->latest();
    }
}
