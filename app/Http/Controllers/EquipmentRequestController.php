<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksSiteTeamLeadership;
use App\Models\EquipmentRequest;
use App\Models\WorkOrder;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EquipmentRequestController extends Controller
{
    use ChecksSiteTeamLeadership;

    public function index(Request $request): View
    {
        $equipmentRequests = $this->filtered($request)
            ->with(['workOrder', 'requestedBy', 'approvedBy', 'asset'])
            ->orderBy($request->get('sort', 'created_at'), $request->get('direction', 'desc') === 'asc' ? 'asc' : 'desc')
            ->paginate(20)
            ->withQueryString();

        $workOrders = WorkOrder::orderByDesc('created_at')->limit(200)->get();

        return view('assets.equipment-requests.index', compact('equipmentRequests', 'workOrders'));
    }

    public function pdf(Request $request)
    {
        $equipmentRequests = $this->filtered($request)
            ->with(['workOrder', 'requestedBy', 'approvedBy', 'asset'])
            ->orderByDesc('created_at')
            ->get();

        $pdf = Pdf::loadView('assets.equipment-requests.pdf', compact('equipmentRequests'));

        return $pdf->download('equipment-requests.pdf');
    }

    private function filtered(Request $request)
    {
        $user = $request->user();
        $ledWorkOrderIds = $user->hasRole('Admin') || $user->hasRole('Management') ? null : WorkOrder::whereHas('executiveTeams', fn ($q) => $q->whereNull('unassigned_at')
            ->whereHas('executiveTeam', fn ($q2) => $q2->where('team_leader_id', $user->id)))
            ->pluck('id');

        return EquipmentRequest::query()
            ->when($ledWorkOrderIds !== null, fn ($q) => $q->whereIn('work_order_id', $ledWorkOrderIds))
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('item_name', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")))
            ->when($request->get('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->get('work_order_id'), fn ($q, $v) => $q->where('work_order_id', $v))
            ->when($request->get('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($request->get('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
    }

    /**
     * Executive Team Leader requests are pinned to a site they lead;
     * Admin/Management may request on behalf of any work order (or none,
     * for company-store equipment that isn't site-specific yet).
     */
    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'item_name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string'],
            'required_by_date' => ['nullable', 'date'],
        ]);

        if (! $user->hasRole('Admin') && ! $user->hasRole('Management')) {
            abort_unless($this->isTeamLeaderOfWorkOrder($data['work_order_id'] ?? null, $user), 403, 'You can only request equipment for a site you lead.');
        }

        EquipmentRequest::create($data + [
            'requested_by' => $user->id,
            'status' => 'requested',
        ]);

        return back()->with('success', 'Equipment request submitted.');
    }

    public function approve(Request $request, EquipmentRequest $equipmentRequest): RedirectResponse
    {
        abort_unless($equipmentRequest->status === 'requested', 422, 'This request has already been decided.');

        $data = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
            'needs_purchase' => ['required_if:decision,approved', 'nullable', 'boolean'],
            'rejection_reason' => ['required_if:decision,rejected', 'nullable', 'string'],
        ]);

        $equipmentRequest->update([
            'status' => $data['decision'] === 'rejected'
                ? 'rejected'
                : ($request->boolean('needs_purchase') ? 'purchase_required' : 'available'),
            'needs_purchase' => $data['decision'] === 'approved' ? $request->boolean('needs_purchase') : null,
            'rejection_reason' => $data['rejection_reason'] ?? null,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
        ]);

        return back()->with('success', 'Equipment request '.$data['decision'].'.');
    }

    /**
     * Moves a request from "Purchase Required" to "Available" once
     * procurement is done, optionally linking the specific Asset that will
     * be dispatched.
     */
    public function markAvailable(Request $request, EquipmentRequest $equipmentRequest): RedirectResponse
    {
        abort_unless($equipmentRequest->status === 'purchase_required', 422, 'This request is not awaiting purchase.');

        $data = $request->validate(['asset_id' => ['nullable', 'exists:assets,id']]);

        $equipmentRequest->update(['status' => 'available', 'asset_id' => $data['asset_id'] ?? null]);

        return back()->with('success', 'Equipment marked available for dispatch.');
    }

    public function dispatch(Request $request, EquipmentRequest $equipmentRequest): RedirectResponse
    {
        abort_unless($equipmentRequest->status === 'available', 422, 'This request is not ready for dispatch.');

        $data = $request->validate(['asset_id' => ['nullable', 'exists:assets,id']]);

        $equipmentRequest->update([
            'status' => 'dispatched',
            'asset_id' => $data['asset_id'] ?? $equipmentRequest->asset_id,
        ]);

        return back()->with('success', 'Equipment dispatched.');
    }

    /**
     * The requesting site's Team Leader (or Admin/Management) confirms the
     * equipment has physically arrived.
     */
    public function receive(Request $request, EquipmentRequest $equipmentRequest): RedirectResponse
    {
        abort_unless($equipmentRequest->status === 'dispatched', 422, 'This request has not been dispatched yet.');

        $user = $request->user();
        if (! $user->hasRole('Admin') && ! $user->hasRole('Management')) {
            abort_unless($this->isTeamLeaderOfWorkOrder($equipmentRequest->work_order_id, $user), 403, 'You can only receive equipment for a site you lead.');
        }

        $equipmentRequest->update(['status' => 'received']);

        return back()->with('success', 'Equipment receipt confirmed.');
    }

    public function complete(Request $request, EquipmentRequest $equipmentRequest): RedirectResponse
    {
        abort_unless($equipmentRequest->status === 'received', 422, 'This request has not been received yet.');

        $user = $request->user();
        if (! $user->hasRole('Admin') && ! $user->hasRole('Management')) {
            abort_unless($this->isTeamLeaderOfWorkOrder($equipmentRequest->work_order_id, $user), 403, 'You can only complete equipment requests for a site you lead.');
        }

        $equipmentRequest->update(['status' => 'completed']);

        return back()->with('success', 'Equipment request completed.');
    }

    /**
     * Admin can cancel a request any time before it's dispatched; the
     * original requester can withdraw their own request only while it's
     * still awaiting a decision.
     */
    public function cancel(Request $request, EquipmentRequest $equipmentRequest): RedirectResponse
    {
        abort_if(in_array($equipmentRequest->status, ['dispatched', 'received', 'completed', 'cancelled'], true), 422, 'This request can no longer be cancelled.');

        $user = $request->user();
        $isOwnPendingRequest = $equipmentRequest->requested_by === $user->id && $equipmentRequest->status === 'requested';
        abort_unless($user->hasRole('Admin') || $isOwnPendingRequest, 403, 'You can only cancel your own request before it has been decided.');

        $equipmentRequest->update(['status' => 'cancelled']);

        return back()->with('success', 'Equipment request cancelled.');
    }
}
