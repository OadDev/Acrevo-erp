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
            'inspection_date' => ['required', 'date'],
            'inspection_type' => ['required', 'in:daily,final'],
            'status' => ['required', 'in:passed,failed,rework_required'],
            'remarks' => ['nullable', 'string'],
        ]);

        $workOrder = WorkOrder::findOrFail($data['work_order_id']);

        // Final QC is the gate that follows "Work Completed — Submit for QC"
        // (which puts the work order into qc_pending) - it can't be recorded
        // before the executive team has actually submitted the work.
        if ($data['inspection_type'] === 'final') {
            abort_unless($workOrder->status === 'qc_pending', 422, 'Final QC can only be recorded after the work order has been submitted for QC.');
        }

        $workOrder->qcInspections()->create($data + [
            'inspected_by' => $request->user()->id,
        ]);

        // Daily QC checks each day's work as it happens and never drives the
        // work order's overall status - only Final QC does. Recording a
        // daily inspection here used to unconditionally move the work order
        // to client_review (making it look "completed" after day one), which
        // is exactly what this guard prevents.
        if ($data['inspection_type'] === 'daily') {
            return redirect()->route('qc.index')->with('success', 'Daily QC inspection recorded.');
        }

        match (true) {
            $data['status'] === 'passed' => $workOrder->transitionTo('client_review', 'Final QC passed — routed to client for final confirmation.'),
            $data['status'] === 'failed' => $workOrder->transitionTo('qc_failed', 'Final QC failed: '.($data['remarks'] ?? '')),
            default => $workOrder->transitionTo('rework_in_progress', 'Rework required after final QC.'),
        };

        return redirect()->route('qc.index')->with('success', 'QC inspection recorded.');
    }

    public function show(QcInspection $qcInspection): View
    {
        $qcInspection->load(['workOrder.client', 'inspectedBy']);

        return view('qc.show', compact('qcInspection'));
    }

    public function edit(QcInspection $qcInspection): View
    {
        $this->authorizeAdminOnly();

        $qcInspection->load('workOrder');

        return view('qc.edit', compact('qcInspection'));
    }

    public function update(Request $request, QcInspection $qcInspection): RedirectResponse
    {
        $this->authorizeAdminOnly();

        $data = $request->validate([
            'inspection_date' => ['required', 'date'],
            'inspection_type' => ['required', 'in:daily,final'],
            'status' => ['required', 'in:passed,failed,rework_required'],
            'remarks' => ['nullable', 'string'],
        ]);

        $qcInspection->update($data);

        return redirect()->route('qc.show', $qcInspection)->with('success', 'QC inspection updated.');
    }

    public function destroy(QcInspection $qcInspection): RedirectResponse
    {
        $this->authorizeAdminOnly();

        $workOrder = $qcInspection->workOrder;

        $qcInspection->delete();

        return $workOrder
            ? redirect()->route('work-orders.show', $workOrder)->with('success', 'QC inspection removed.')
            : redirect()->route('qc.index')->with('success', 'QC inspection removed.');
    }
}
