@php
    $canSeeThisWosTickets = auth()->user()->can('tickets.all_view')
        || (auth()->user()->can('tickets.assigned_view') && $workOrder->isAssignedToUser(auth()->user()));
@endphp
<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-3 lg:col-span-2">
        @if ($canSeeThisWosTickets)
            @forelse ($workOrder->tickets as $ticket)
                <a href="{{ route('tickets.show', $ticket) }}">
                    <x-card class="transition hover:border-indigo-300">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $ticket->ticket_no }} — {{ $ticket->title }}</p>
                                <p class="text-xs text-gray-400">{{ Str::title($ticket->type) }} · {{ Str::title($ticket->priority) }} priority</p>
                            </div>
                            <x-badge :status="$ticket->status" />
                        </div>
                    </x-card>
                </a>
            @empty
                <x-empty-state icon="ticket" title="No tickets raised" />
            @endforelse
        @else
            <x-empty-state icon="ticket" title="No tickets to show" description="You don't have permission to view tickets on this Work Order." />
        @endif
    </div>

    @if ($workOrder->canHaveTicketRaisedBy(auth()->user()))
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Raise a Ticket</h3>
            <x-link-button :href="route('tickets.create', ['work_order_id' => $workOrder->id])" class="w-full justify-center">New Ticket</x-link-button>
        </x-card>
    @endif
</div>
