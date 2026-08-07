<?php

namespace App\Http\Controllers;

use App\Http\Requests\WorkOrderRequest;
use App\Models\ExecutiveTeam;
use App\Models\Quotation;
use App\Models\WorkOrder;
use App\Models\WorkOrderExecutiveTeam;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    public function index(Request $request): View
    {
        $workOrders = WorkOrder::query()
            ->with(['client', 'executiveTeams.executiveTeam'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('title', 'like', "%{$search}%")
                ->orWhere('work_order_no', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('work-orders.index', compact('workOrders'));
    }

    public function completed(): View
    {
        $workOrders = WorkOrder::query()
            ->with('client')
            ->whereIn('status', ['completed', 'cancelled'])
            ->latest()
            ->paginate(15);

        return view('work-orders.completed', compact('workOrders'));
    }

    public function create(Request $request): View
    {
        $quotation = $request->get('quotation_id') ? Quotation::with('client')->find($request->get('quotation_id')) : null;

        return view('work-orders.create', compact('quotation'));
    }

    public function store(WorkOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $quotation = ! empty($data['quotation_id']) ? Quotation::find($data['quotation_id']) : null;

        $workOrder = WorkOrder::create($data + [
            'enquiry_id' => $quotation?->enquiry_id,
            'type' => 'new',
            'status' => 'pending_hr_assignment',
            'created_by' => $request->user()->id,
        ]);

        $quotation?->enquiry?->update(['status' => 'converted']);

        return redirect()->route('work-orders.show', $workOrder)->with('success', 'Work order generated successfully.');
    }

    public function show(WorkOrder $workOrder): View
    {
        $this->authorize('view', $workOrder);

        $workOrder->load([
            'client', 'quotation', 'statusLogs.changedBy', 'executiveTeams.executiveTeam.teamLeader',
            'tickets', 'qcInspections.inspectedBy',
            'dailyChecklists' => fn ($q) => $q->latest(),
            'dailyProgressReports' => fn ($q) => $q->latest(),
            'materialEntries.addedBy', 'labourEntries.employee', 'measurementBooks.items', 'ledgers',
            'children', 'parent', 'clientReviews',
            'media',
        ]);

        $availableTeams = ExecutiveTeam::where('is_active', true)->get();

        return view('work-orders.show', compact('workOrder', 'availableTeams'));
    }

    public function cancel(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('cancel', $workOrder);

        $data = $request->validate(['remarks' => ['nullable', 'string']]);
        $workOrder->transitionTo('cancelled', $data['remarks'] ?? 'Work order cancelled.');

        return back()->with('success', 'Work order cancelled.');
    }

    public function assignTeam(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate(['executive_team_id' => ['required', 'exists:executive_teams,id']]);

        WorkOrderExecutiveTeam::create([
            'work_order_id' => $workOrder->id,
            'executive_team_id' => $data['executive_team_id'],
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        if ($workOrder->status === 'pending_hr_assignment') {
            $workOrder->transitionTo('team_assigned', 'Executive team assigned by HR.');
        }

        return back()->with('success', 'Executive team assigned.');
    }

    public function unassignTeam(WorkOrder $workOrder, WorkOrderExecutiveTeam $assignment): RedirectResponse
    {
        $assignment->update(['unassigned_at' => now()]);

        return back()->with('success', 'Executive team unassigned.');
    }

    public function createRework(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
        ]);

        $rework = WorkOrder::create($data + [
            'client_id' => $workOrder->client_id,
            'parent_work_order_id' => $workOrder->id,
            'type' => 'rework',
            'priority' => 'high',
            'status' => 'pending_hr_assignment',
            'created_by' => $request->user()->id,
        ]);

        $workOrder->transitionTo('rework_in_progress', 'Re-work order created: '.$rework->work_order_no);

        return redirect()->route('work-orders.show', $rework)->with('success', 'Re-work order created.');
    }

    public function createNext(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
        ]);

        $next = WorkOrder::create($data + [
            'client_id' => $workOrder->client_id,
            'parent_work_order_id' => $workOrder->id,
            'type' => 'next',
            'priority' => 'medium',
            'status' => 'pending_hr_assignment',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('work-orders.show', $next)->with('success', 'Next work order created.');
    }

    public function complete(WorkOrder $workOrder): RedirectResponse
    {
        $workOrder->transitionTo('completed', 'Work order marked complete after final QC and client confirmation.');

        $workOrder->completionCertificates()->create([
            'issued_date' => now(),
            'issued_by' => auth()->id(),
        ]);

        return back()->with('success', 'Work order marked as completed.');
    }

    public function storeFeedback(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('view', $workOrder);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comments' => ['nullable', 'string'],
        ]);

        $workOrder->clientReviews()->create($data + [
            'client_id' => $workOrder->client_id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Client feedback recorded.');
    }
}
