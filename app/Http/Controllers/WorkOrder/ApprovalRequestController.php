<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\WorkOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class ApprovalRequestController extends Controller
{
    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx'],
        ]);

        $approvalRequest = $workOrder->approvalRequests()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'direction' => 'company_to_client',
            'requested_by' => $request->user()->id,
            'status' => 'pending',
        ]);

        if ($request->hasFile('file')) {
            try {
                $approvalRequest->addMediaFromRequest('file')->toMediaCollection('attachment');
            } catch (FileIsTooBig $e) {
                return back()->withErrors(['file' => 'That file is too large (max 20MB).']);
            }
        }

        return back()->with('success', 'Approval request sent to the client.');
    }

    public function respond(Request $request, WorkOrder $workOrder, ApprovalRequest $approvalRequest): RedirectResponse
    {
        abort_unless($approvalRequest->work_order_id === $workOrder->id, 404);
        abort_unless($approvalRequest->direction === 'client_to_company', 403, 'This request is awaiting the client\'s response, not ours.');
        abort_unless($approvalRequest->status === 'pending', 422, 'This request has already been responded to.');

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'response_note' => ['nullable', 'string'],
        ]);

        $approvalRequest->update($data + [
            'responded_by' => $request->user()->id,
            'responded_at' => now(),
        ]);

        return back()->with('success', 'Response recorded.');
    }

    public function update(Request $request, WorkOrder $workOrder, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($approvalRequest->work_order_id === $workOrder->id, 404);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $approvalRequest->update($data);

        return back()->with('success', 'Approval request updated.');
    }

    public function destroy(WorkOrder $workOrder, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($approvalRequest->work_order_id === $workOrder->id, 404);

        $approvalRequest->delete();

        return back()->with('success', 'Approval request removed.');
    }

    public function pdf(WorkOrder $workOrder, ApprovalRequest $approvalRequest)
    {
        abort_unless($approvalRequest->work_order_id === $workOrder->id, 404);

        $approvalRequest->load(['workOrder.client', 'requestedBy', 'requestedByClient', 'respondedBy']);

        $pdf = Pdf::loadView('work-orders.approval-requests.pdf', compact('approvalRequest'));

        return $pdf->download("{$approvalRequest->approval_no}.pdf");
    }

    public function approvedPdf(WorkOrder $workOrder)
    {
        $approvalRequests = $workOrder->approvalRequests()
            ->where('status', 'approved')
            ->with(['requestedBy', 'requestedByClient', 'respondedBy'])
            ->orderBy('responded_at')
            ->get();

        $pdf = Pdf::loadView('work-orders.approval-requests.approved-pdf', compact('workOrder', 'approvalRequests'));

        return $pdf->download("{$workOrder->work_order_no}-approved-requests.pdf");
    }
}
