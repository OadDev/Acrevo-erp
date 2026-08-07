<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Tickets" subtitle="Delays, material issues, client changes, and quality concerns.">
            <x-slot name="actions">
                <x-select-input name="status" class="text-sm" onchange="location.href='{{ route('tickets.index') }}?status='+this.value">
                    <option value="">All Statuses</option>
                    @foreach (['open', 'in_progress', 'resolved', 'closed'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::title(str_replace('_',' ',$status)) }}</option>
                    @endforeach
                </x-select-input>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card :padded="false">
        @if ($tickets->isEmpty())
            <div class="p-6"><x-empty-state icon="ticket" title="No tickets" description="Tickets are raised from a work order when issues come up." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Ticket</th>
                            <th class="px-5 py-3">Work Order</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Priority</th>
                            <th class="px-5 py-3">Assigned To</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($tickets as $ticket)
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('tickets.show', $ticket) }}'">
                                <td class="px-5 py-3">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $ticket->ticket_no }}</span>
                                    <p class="text-xs text-gray-400">{{ $ticket->title }}</p>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $ticket->workOrder->work_order_no }}</td>
                                <td class="px-5 py-3"><x-badge color="indigo" :status="$ticket->type" /></td>
                                <td class="px-5 py-3"><x-badge :status="$ticket->priority" /></td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $ticket->assignedTo?->name ?? '—' }}</td>
                                <td class="px-5 py-3"><x-badge :status="$ticket->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $tickets->links() }}</div>
</x-app-layout>
