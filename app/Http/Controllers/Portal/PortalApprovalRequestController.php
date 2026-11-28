<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ApprovalRequest;
use App\Models\ApprovalRequestComment;
use App\Models\WorkOrder;
use App\Services\WorkOrderZipExporter;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class PortalApprovalRequestController extends Controller
{
    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $client = $request->user()->client();

        abort_unless($client && $workOrder->client_id === $client->id, 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx,mp4,mov,avi'],
        ]);

        $approvalRequest = $workOrder->approvalRequests()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'direction' => 'client_to_company',
            'requested_by_client_id' => $client->id,
            'status' => 'pending',
        ]);

        try {
            foreach ($request->file('files', []) as $file) {
                $approvalRequest->addMedia($file)->toMediaCollection('attachment');
            }
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
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

    public function addComment(Request $request, WorkOrder $workOrder, ApprovalRequest $approvalRequest): RedirectResponse
    {
        $client = $request->user()->client();

        abort_unless($client && $workOrder->client_id === $client->id, 403);
        abort_unless($approvalRequest->work_order_id === $workOrder->id, 404);

        $data = $request->validate([
            'comment' => ['required', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx,mp4,mov,avi'],
        ]);

        $comment = $approvalRequest->comments()->create([
            'comment' => $data['comment'],
            'user_id' => $request->user()->id,
        ]);

        try {
            foreach ($request->file('files', []) as $file) {
                $comment->addMedia($file)->toMediaCollection('attachments');
            }
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
        }

        return back()->with('success', 'Comment added.');
    }

    public function updateComment(Request $request, WorkOrder $workOrder, ApprovalRequest $approvalRequest, ApprovalRequestComment $comment): RedirectResponse
    {
        $client = $request->user()->client();

        abort_unless($client && $workOrder->client_id === $client->id, 403);
        abort_unless($comment->approval_request_id === $approvalRequest->id && $approvalRequest->work_order_id === $workOrder->id, 404);
        $this->authorize('update', $comment);

        $data = $request->validate([
            'comment' => ['required', 'string'],
            'remove_media_ids' => ['nullable', 'array'],
            'remove_media_ids.*' => ['integer'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx,mp4,mov,avi'],
        ]);

        $comment->update(['comment' => $data['comment'], 'edited_at' => now()]);

        if (! empty($data['remove_media_ids']) || ! empty($data['files'])) {
            $this->authorize('manageAttachments', $comment);
        }

        foreach ($data['remove_media_ids'] ?? [] as $mediaId) {
            $comment->media->firstWhere('id', (int) $mediaId)?->delete();
        }

        try {
            foreach ($request->file('files', []) as $file) {
                $comment->addMedia($file)->toMediaCollection('attachments');
            }
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
        }

        return back()->with('success', 'Comment updated.');
    }

    public function destroyComment(Request $request, WorkOrder $workOrder, ApprovalRequest $approvalRequest, ApprovalRequestComment $comment): RedirectResponse
    {
        $client = $request->user()->client();

        abort_unless($client && $workOrder->client_id === $client->id, 403);
        abort_unless($comment->approval_request_id === $approvalRequest->id && $approvalRequest->work_order_id === $workOrder->id, 404);
        $this->authorize('delete', $comment);

        $comment->delete();

        return back()->with('success', 'Comment removed.');
    }

    public function destroyCommentMedia(Request $request, WorkOrder $workOrder, ApprovalRequest $approvalRequest, ApprovalRequestComment $comment, Media $media): RedirectResponse
    {
        $client = $request->user()->client();

        abort_unless($client && $workOrder->client_id === $client->id, 403);
        abort_unless($comment->approval_request_id === $approvalRequest->id && $approvalRequest->work_order_id === $workOrder->id, 404);
        $this->authorize('deleteAttachment', $comment);
        abort_unless((string) $media->model_id === (string) $comment->id && $media->model_type === ApprovalRequestComment::class, 404);

        $media->delete();

        return back()->with('success', 'Attachment removed.');
    }

    public function pdf(Request $request, WorkOrder $workOrder, ApprovalRequest $approvalRequest)
    {
        $client = $request->user()->client();

        abort_unless($client && $workOrder->client_id === $client->id, 403);
        abort_unless($approvalRequest->work_order_id === $workOrder->id, 404);

        $approvalRequest->load(['workOrder.client', 'requestedBy', 'requestedByClient', 'respondedBy', 'media']);

        $pdf = Pdf::loadView('work-orders.approval-requests.pdf', compact('approvalRequest'));

        return $pdf->download("{$approvalRequest->approval_no}.pdf");
    }

    public function zip(Request $request, WorkOrder $workOrder, ApprovalRequest $approvalRequest, WorkOrderZipExporter $exporter): BinaryFileResponse
    {
        $client = $request->user()->client();

        abort_unless($client && $workOrder->client_id === $client->id, 403);
        abort_unless($approvalRequest->work_order_id === $workOrder->id, 404);

        $zipPath = sys_get_temp_dir().'/approval-zip-'.Str::random(20).'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $exporter->addApprovalRequest($zip, $approvalRequest);
        $zip->close();

        return response()->download($zipPath, "{$approvalRequest->approval_no}-attachments.zip", [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ])->deleteFileAfterSend();
    }

    public function approvedPdf(Request $request, WorkOrder $workOrder)
    {
        $client = $request->user()->client();

        abort_unless($client && $workOrder->client_id === $client->id, 403);

        $approvalRequests = $workOrder->approvalRequests()
            ->where('status', 'approved')
            ->with(['requestedBy', 'requestedByClient', 'respondedBy', 'media'])
            ->orderBy('responded_at')
            ->get();

        $pdf = Pdf::loadView('work-orders.approval-requests.approved-pdf', compact('workOrder', 'approvalRequests'));

        return $pdf->download("{$workOrder->work_order_no}-approved-requests.pdf");
    }
}
