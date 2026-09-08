<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetRepair;
use App\Models\AssetStatusLog;
use App\Models\WorkOrder;
use App\Support\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkOrderEquipmentController extends Controller
{
    /**
     * Current equipment at this site - every Asset with an Available
     * quantity in the AssetStock ledger at this work order - plus every
     * Movement still pending confirmation into this site, so a Team Leader
     * can see and act on an incoming assignment right here instead of
     * having to find it in the wider Movement History list.
     */
    public function index(Request $request, WorkOrder $workOrder): View
    {
        $this->authorize('view', $workOrder);

        $assets = $this->currentEquipment($request, $workOrder)->paginate(20)->withQueryString();

        $pendingMovements = AssetMovement::query()
            ->where('to_work_order_id', $workOrder->id)
            ->where('status', 'pending')
            ->with(['asset' => fn ($q) => $q->withTrashed(), 'fromWorkOrder', 'createdBy'])
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->get();

        $user = $request->user();
        $ledWorkOrderIds = $user->hasRole('Admin') ? null : WorkOrder::whereHas('executiveTeams', fn ($q) => $q->whereNull('unassigned_at')
            ->whereHas('executiveTeam', fn ($q2) => $q2->where('team_leader_id', $user->id)))
            ->pluck('id');

        $canConfirmHere = $user->can('movements.approve') && ($ledWorkOrderIds === null || $ledWorkOrderIds->contains($workOrder->id));

        return view('work-orders.equipment.index', compact('workOrder', 'assets', 'pendingMovements', 'canConfirmHere'));
    }

    public function pdf(Request $request, WorkOrder $workOrder)
    {
        $this->authorize('view', $workOrder);

        $assets = $this->currentEquipment($request, $workOrder)->get();

        $pdf = Pdf::loadView('work-orders.equipment.pdf', compact('workOrder', 'assets'));

        return $pdf->download("{$workOrder->work_order_no}-equipment.pdf");
    }

    /**
     * The broader history: everything ever assigned/transferred/returned
     * for this site (Movements touching it either end), every repair
     * logged while an asset was here, and every time an asset was found
     * missing while assigned here. "Received in period" is just the
     * confirmed-arrival movements within the date filter below.
     */
    public function history(Request $request, WorkOrder $workOrder): View
    {
        $this->authorize('view', $workOrder);

        $movements = $this->movementsQuery($request, $workOrder)->paginate(20, ['*'], 'movements_page')->withQueryString();
        $repairs = $this->repairsQuery($request, $workOrder)->paginate(20, ['*'], 'repairs_page')->withQueryString();
        $missingLogs = $this->missingLogsQuery($request, $workOrder)->paginate(20, ['*'], 'missing_page')->withQueryString();

        return view('work-orders.equipment.history', compact('workOrder', 'movements', 'repairs', 'missingLogs'));
    }

    public function historyPdf(Request $request, WorkOrder $workOrder)
    {
        $this->authorize('view', $workOrder);

        $movements = $this->movementsQuery($request, $workOrder)->get();
        $repairs = $this->repairsQuery($request, $workOrder)->get();
        $missingLogs = $this->missingLogsQuery($request, $workOrder)->get();

        $pdf = Pdf::loadView('work-orders.equipment.history-pdf', compact('workOrder', 'movements', 'repairs', 'missingLogs'));

        return $pdf->download("{$workOrder->work_order_no}-equipment-history.pdf");
    }

    /**
     * Every Asset holding Available quantity at this work order in the
     * AssetStock ledger - not just those whose legacy current_work_order_id
     * happens to point here, since that field only follows a Movement that
     * covers an asset's entire available stock (see AssetMovementController
     * ::confirm()). A partial quantity confirmed into this site still shows
     * up here even though current_work_order_id may still point elsewhere.
     */
    private function currentEquipment(Request $request, WorkOrder $workOrder)
    {
        return Asset::query()
            ->whereHas('stocks', fn ($q) => $q->where('location', 'work_order')
                ->where('work_order_id', $workOrder->id)
                ->where('status', 'available')
                ->where('quantity', '>', 0))
            ->with(['stocks' => fn ($q) => $q->where('location', 'work_order')
                ->where('work_order_id', $workOrder->id)
                ->where('status', 'available')])
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('asset_code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")))
            ->when($request->get('status'), fn ($q, $v) => $q->where('status', $v))
            ->orderBy($request->get('sort', 'name'), $request->get('direction', 'asc') === 'desc' ? 'desc' : 'asc');
    }

    private function movementsQuery(Request $request, WorkOrder $workOrder)
    {
        return AssetMovement::query()
            ->where(fn ($q) => $q->where('from_work_order_id', $workOrder->id)->orWhere('to_work_order_id', $workOrder->id))
            ->with(['asset' => fn ($q) => $q->withTrashed(), 'fromWorkOrder', 'toWorkOrder', 'createdBy', 'confirmedBy'])
            ->when($request->get('q'), fn ($q, $search) => $q->whereHas('asset', fn ($q2) => $q2
                ->where('asset_code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->when($request->get('from'), fn ($q, $v) => $q->whereDate('moved_at', '>=', $v))
            ->when($request->get('to'), fn ($q, $v) => $q->whereDate('moved_at', '<=', $v))
            ->orderByDesc('moved_at')
            ->orderByDesc('id');
    }

    private function repairsQuery(Request $request, WorkOrder $workOrder)
    {
        return AssetRepair::query()
            ->where('work_order_id', $workOrder->id)
            ->with(['asset' => fn ($q) => $q->withTrashed(), 'createdBy'])
            ->when($request->get('q'), fn ($q, $search) => $q->whereHas('asset', fn ($q2) => $q2
                ->where('asset_code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->when($request->get('from'), fn ($q, $v) => $q->whereDate('reported_date', '>=', $v))
            ->when($request->get('to'), fn ($q, $v) => $q->whereDate('reported_date', '<=', $v))
            ->orderByDesc('reported_date')
            ->orderByDesc('id');
    }

    private function missingLogsQuery(Request $request, WorkOrder $workOrder)
    {
        return AssetStatusLog::query()
            ->where('work_order_id', $workOrder->id)
            ->where('new_status', 'missing')
            ->with(['asset' => fn ($q) => $q->withTrashed(), 'updatedBy'])
            ->when($request->get('q'), fn ($q, $search) => $q->whereHas('asset', fn ($q2) => $q2
                ->where('asset_code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->when($request->get('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->get('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at');
    }
}
