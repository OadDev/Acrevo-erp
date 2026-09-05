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
     * Current equipment at this site - every Asset whose
     * current_work_order_id points here right now.
     */
    public function index(Request $request, WorkOrder $workOrder): View
    {
        $this->authorize('view', $workOrder);

        $assets = $this->currentEquipment($request, $workOrder)->paginate(20)->withQueryString();

        return view('work-orders.equipment.index', compact('workOrder', 'assets'));
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

    private function currentEquipment(Request $request, WorkOrder $workOrder)
    {
        return Asset::query()
            ->where('current_work_order_id', $workOrder->id)
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
            ->with(['asset', 'fromWorkOrder', 'toWorkOrder', 'createdBy', 'confirmedBy'])
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
            ->with(['asset', 'createdBy'])
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
            ->with(['asset', 'updatedBy'])
            ->when($request->get('q'), fn ($q, $search) => $q->whereHas('asset', fn ($q2) => $q2
                ->where('asset_code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->when($request->get('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->get('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at');
    }
}
