<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class PortalApprovalRequestController extends Controller
{
    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $client = $request->user()->client();

        abort_unless($client && $workOrder->client_id === $client->id, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'file' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx'],
        ]);

        $approvalRequest = $workOrder->approvalRequests()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'direction' => 'client_to_company',
            'requested_by_client_id' => $client->id,
            'status' => 'pending',
        ]);

        if ($request->hasFile('file')) {
            try {
                $approvalRequest->addMediaFromRequest('file')->toMediaCollection('attachment');
            } catch (FileIsTooBig $e) {
                return back()->withErrors(['file' => 'That file is too large (max 20MB).']);
            }
        }

        return back()->with('success', 'Approval request sent to our team.');
    }

    public function respond(Request $request, WorkOrder $workOrder, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $client = $request->user()->client();

        abort_unless($client && $workOrder->client_id === $client->id, 403);
        abort_unless($approvalRequest->work_order_id === $workOrder->id, 404);
        abort_unless($approvalRequest->direction === 'company_to_client', 403, 'This request is awaiting our team\'s response, not yours.');
        abort_unless($approvalRequest->status === 'pending', 422, 'This request has already been responded to.');

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'response_note' => ['nullable', 'string'],
        ]);

        $approvalRequest->update($data + [
            'responded_by' => $request->user()->id,
            'responded_at' => now(),
        ]);

        return back()->with('success', 'Your response has been recorded.');
    }
}
