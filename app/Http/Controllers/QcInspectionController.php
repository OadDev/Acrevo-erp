<?php

namespace App\Http\Controllers;

use App\Models\QcInspection;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QcInspectionController extends Controller
{
    public function index(Request $request): View
    {
        $inspections = QcInspection::query()
            ->with(['workOrder.client', 'inspectedBy'])
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('qc.index', compact('inspections'));
    }

    public function create(Request $request): View
    {
        $workOrder = WorkOrder::with('client')->findOrFail($request->get('work_order_id'));

        return view('qc.create', compact('workOrder'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_order_id' => ['required', 'exists:work_orders,id'],
            'inspection_type' => ['required', 'in:daily,final'],
            'status' => ['required', 'in:passed,failed,rework_required'],
            'remarks' => ['nullable', 'string'],
        ]);

        $workOrder = WorkOrder::findOrFail($data['work_order_id']);

        $workOrder->qcInspections()->create($data + [
            'inspection_date' => now()->toDateString(),
            'inspected_by' => $request->user()->id,
        ]);

        match (true) {
            $data['status'] === 'passed' => $workOrder->transitionTo(
                'client_review',
                $data['inspection_type'] === 'final' ? 'Final QC passed — routed to client review.' : 'Daily QC passed — routed to client review.'
            ),
            $data['status'] === 'failed' => $workOrder->transitionTo('qc_failed', 'QC failed: '.($data['remarks'] ?? '')),
            default => $workOrder->transitionTo('rework_in_progress', 'Rework required after QC.'),
        };

        return redirect()->route('qc.index')->with('success', 'QC inspection recorded.');
    }

    public function show(QcInspection $qcInspection): View
    {
        $qcInspection->load(['workOrder.client', 'inspectedBy']);

        return view('qc.show', compact('qcInspection'));
    }
}
