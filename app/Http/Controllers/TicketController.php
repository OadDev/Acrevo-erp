<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketRequest;
use App\Models\Department;
use App\Models\Ticket;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TicketController extends Controller
{
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

        $ticket->workOrder->transitionTo('ticket_raised', 'Ticket raised: '.$ticket->title);

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket raised successfully.');
    }

    public function show(Ticket $ticket): View
    {
        $this->authorize('view', $ticket);

        $ticket->load(['workOrder.client', 'raisedBy', 'assignedTo', 'department', 'comments.user']);

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

        $ticket->update($request->validated());

        return redirect()->route('tickets.show', $ticket)->with('success', 'Ticket updated.');
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

        if (in_array($data['status'], ['resolved', 'closed']) && $ticket->workOrder->status === 'ticket_raised') {
            $ticket->workOrder->transitionTo('rework_in_progress', 'Ticket resolved — resuming work.');
        }

        return back()->with('success', 'Ticket status updated.');
    }
}
