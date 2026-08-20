<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

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
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx'],
        ]);

        $workOrder = WorkOrder::findOrFail($data['work_order_id']);
        abort_unless($workOrder->client_id === $client->id, 403);

        $ticket = Ticket::create(collect($data)->except('files')->all() + [
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

        $workOrder->transitionTo('ticket_raised', 'Client raised a ticket: '.$ticket->title);

        return redirect()->route('portal.tickets.index')->with('success', 'Ticket submitted. Our team will get back to you shortly.');
    }
}
