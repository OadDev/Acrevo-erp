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
                            @can('tickets.manage')
                                <th class="px-5 py-3"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($tickets as $ticket)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="cursor-pointer px-5 py-3" onclick="window.location='{{ route('tickets.show', $ticket) }}'">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $ticket->ticket_no }}</span>
                                    <p class="text-xs text-gray-400">{{ $ticket->title }}</p>
                                </td>
                                <td class="cursor-pointer px-5 py-3 text-sm text-gray-600 dark:text-gray-300" onclick="window.location='{{ route('tickets.show', $ticket) }}'">{{ $ticket->workOrder->work_order_no ?? 'Deleted work order' }}</td>
                                <td class="cursor-pointer px-5 py-3" onclick="window.location='{{ route('tickets.show', $ticket) }}'"><x-badge color="indigo" :status="$ticket->type" /></td>
                                <td class="cursor-pointer px-5 py-3" onclick="window.location='{{ route('tickets.show', $ticket) }}'"><x-badge :status="$ticket->priority" /></td>
                                <td class="cursor-pointer px-5 py-3 text-sm text-gray-600 dark:text-gray-300" onclick="window.location='{{ route('tickets.show', $ticket) }}'">{{ $ticket->assignedTo?->name ?? '—' }}</td>
                                <td class="cursor-pointer px-5 py-3" onclick="window.location='{{ route('tickets.show', $ticket) }}'"><x-badge :status="$ticket->status" /></td>
                                @can('tickets.manage')
                                    <td class="px-5 py-3 text-right">
                                        <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" onsubmit="return confirm('Remove this ticket? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-sm font-medium text-rose-600 hover:underline">Remove</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $tickets->links() }}</div>
</x-app-layout>
