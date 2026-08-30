<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketRequest;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use App\Services\WorkOrderZipExporter;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class TicketController extends Controller
{
    private function attachFiles(Ticket $ticket, Request $request): ?string
    {
        try {
            foreach ($request->file('files', []) as $file) {
                $ticket->addMedia($file)->toMediaCollection('attachments');
            }
        } catch (FileIsTooBig $e) {
            return 'One of those files is too large (max 20MB).';
        }

        return null;
    }

    public function index(Request $request): View
    {
        $tickets = Ticket::query()
            ->with(['workOrder.client', 'assignedTo'])
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('tickets.index', compact('tickets'));
    }

    public function create(Request $request): View
    {
        $workOrder = WorkOrder::with('client')->findOrFail($request->get('work_order_id'));
        $departments = Department::orderBy('name')->get();
        $assignees = User::orderBy('name')->get();

        return view('tickets.create', compact('workOrder', 'departments', 'assignees'));
    }

    public function store(TicketRequest $request): RedirectResponse
    {
        $ticket = Ticket::create($request->validated() + [
            'raised_by_type' => 'internal',
            'raised_by' => $request->user()->id,
            'status' => 'open',
        ]);

        if ($error = $this->attachFiles($ticket, $request)) {
            return back()->withErrors(['files' => $error]);
        }

        $ticket->workOrder->syncStatusFromTickets();

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket raised successfully.');
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load(['workOrder.client', 'raisedBy', 'assignedTo', 'department', 'comments.user', 'media']);

        return view('tickets.show', compact('ticket'));
    }

    public function edit(Ticket $ticket): View
    {
        $this->authorize('update', $ticket);

        $workOrder = $ticket->workOrder()->with('client')->first();

        abort_if($workOrder === null, 404, 'This ticket\'s work order has been deleted and can no longer be edited.');

        $departments = Department::orderBy('name')->get();
        $assignees = User::orderBy('name')->get();

        return view('tickets.edit', compact('ticket', 'workOrder', 'departments', 'assignees'));
    }

    public function update(TicketRequest $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $ticket->update(collect($request->validated())->except('files')->all());

        if ($error = $this->attachFiles($ticket, $request)) {
            return back()->withErrors(['files' => $error]);
        }

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket updated.');
    }

    public function destroy(Ticket $ticket): RedirectResponse
    {
        $workOrder = $ticket->workOrder;

        $ticket->delete();

        $workOrder?->syncStatusFromTickets();

        return redirect()->route('tickets.index')->with('success', 'Ticket removed.');
    }

    public function destroyMedia(Ticket $ticket, Media $media): RedirectResponse
    {
        $this->authorize('update', $ticket);

        abort_unless((string) $media->model_id === (string) $ticket->id, 404);

        $media->delete();

        return back()->with('success', 'File removed.');
    }

    public function lock(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->authorize('lock', $ticket);

        $ticket->update(['locked_at' => now(), 'locked_by' => $request->user()->id]);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket marked uneditable.');
    }

    public function addComment(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['comment' => ['required', 'string']]);

        $ticket->comments()->create($data + ['user_id' => $request->user()->id]);

        return back()->with('success', 'Comment added.');
    }

    public function updateStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', 'in:open,in_progress,resolved,closed']]);

        $ticket->update($data + [
            'resolved_at' => $data['status'] === 'resolved' ? now() : $ticket->resolved_at,
            'closed_at' => $data['status'] === 'closed' ? now() : $ticket->closed_at,
        ]);

        $ticket->workOrder->syncStatusFromTickets();

        return back()->with('success', 'Ticket status updated.');
    }

    public function pdf(Ticket $ticket)
    {
        $this->authorize('view', $ticket);

        $ticket->load(['workOrder.client', 'raisedBy', 'raisedByClient', 'assignedTo', 'department', 'comments.user', 'media']);

        $pdf = Pdf::loadView('tickets.pdf', compact('ticket'));

        return $pdf->download("{$ticket->ticket_no}.pdf");
    }

    public function zip(Ticket $ticket, WorkOrderZipExporter $exporter): BinaryFileResponse
    {
        $this->authorize('view', $ticket);

        $zipPath = sys_get_temp_dir().'/ticket-zip-'.Str::random(20).'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $exporter->addTicket($zip, $ticket);
        $zip->close();

        return response()->download($zipPath, "{$ticket->ticket_no}-attachments.zip", [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ])->deleteFileAfterSend();
    }
}
