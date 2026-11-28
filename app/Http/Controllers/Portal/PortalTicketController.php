<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\WorkOrder;
use App\Support\RichText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class PortalTicketController extends Controller
{
    public function index(Request $request): View
    {
        $client = $request->user()->client();

        abort_unless($client, 403, 'No client account linked to this login.');

        $tickets = Ticket::whereHas('workOrder', fn ($q) => $q->where('client_id', $client->id))
            ->with(['workOrder', 'media'])
            ->latest()
            ->paginate(10);

        $workOrders = WorkOrder::where('client_id', $client->id)->whereNotIn('status', ['completed', 'cancelled'])->get();
        $preselectedWorkOrderId = $request->get('work_order_id');

        return view('portal.tickets.index', compact('tickets', 'workOrders', 'preselectedWorkOrderId'));
    }

    public function store(Request $request): RedirectResponse
    {
        $client = $request->user()->client();

        $data = $request->validate([
            'work_order_id' => ['required', 'exists:work_orders,id'],
            'type' => ['required', 'in:delay,material,client_change,quality,safety,technical,finance,internal'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx,mp4,mov,avi'],
        ]);

        $workOrder = WorkOrder::findOrFail($data['work_order_id']);
        abort_unless($workOrder->client_id === $client->id, 403);

        $ticketData = collect($data)->except('files')->all();
        $ticketData['description'] = RichText::sanitize($ticketData['description'] ?? null);

        $ticket = Ticket::create($ticketData + [
            'priority' => 'medium',
            'raised_by_type' => 'client',
            'raised_by_client_id' => $client->id,
            'status' => 'open',
        ]);

        try {
            foreach ($request->file('files', []) as $file) {
                $ticket->addMedia($file)->toMediaCollection('attachments');
            }
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
        }

        $workOrder->syncStatusFromTickets();

        return redirect()->route('portal.tickets.index')->with('success', 'Ticket submitted. Our team will get back to you shortly.');
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load(['workOrder.client', 'raisedBy', 'raisedByClient', 'assignedTo', 'department', 'media', 'comments.user', 'comments.media']);

        return view('portal.tickets.show', compact('ticket'));
    }

    public function addComment(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('view', $ticket);

        $data = $request->validate([
            'comment' => ['required', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx,mp4,mov,avi'],
        ]);

        $comment = $ticket->comments()->create(collect($data)->except('files')->all() + ['user_id' => $request->user()->id]);

        try {
            foreach ($request->file('files', []) as $file) {
                $comment->addMedia($file)->toMediaCollection('attachments');
            }
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
        }

        return back()->with('success', 'Comment added.');
    }

    public function updateComment(Request $request, Ticket $ticket, TicketComment $comment): RedirectResponse
    {
        $this->authorize('update', $comment);
        abort_unless($comment->ticket_id === $ticket->id, 404);

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

    public function destroyComment(Ticket $ticket, TicketComment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);
        abort_unless($comment->ticket_id === $ticket->id, 404);

        $comment->delete();

        return back()->with('success', 'Comment removed.');
    }

    public function destroyCommentMedia(Ticket $ticket, TicketComment $comment, Media $media): RedirectResponse
    {
        $this->authorize('deleteAttachment', $comment);
        abort_unless($comment->ticket_id === $ticket->id, 404);
        abort_unless((string) $media->model_id === (string) $comment->id && $media->model_type === TicketComment::class, 404);

        $media->delete();

        return back()->with('success', 'Attachment removed.');
    }
}
