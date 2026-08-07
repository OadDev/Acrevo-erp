<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Tickets" subtitle="Raise a concern or track existing ones." />
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card :padded="false" class="lg:col-span-2">
            @if ($tickets->isEmpty())
                <div class="p-6"><x-empty-state icon="ticket" title="No tickets raised yet" /></div>
            @else
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($tickets as $ticket)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $ticket->title }}</p>
                                    <p class="text-xs text-gray-400">{{ $ticket->workOrder->work_order_no }}</p>
                                </td>
                                <td class="px-4 py-3"><x-badge :status="$ticket->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Raise a Ticket</h3>
            <form method="POST" action="{{ route('portal.tickets.store') }}" class="space-y-3">
                @csrf
                <x-select-input name="work_order_id" class="w-full" required>
                    @foreach ($workOrders as $wo)
                        <option value="{{ $wo->id }}" @selected($preselectedWorkOrderId == $wo->id)>{{ $wo->title }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="type" class="w-full">
                    @foreach (['delay', 'material', 'client_change', 'quality', 'safety', 'technical'] as $type)
                        <option value="{{ $type }}">{{ Str::title(str_replace('_',' ',$type)) }}</option>
                    @endforeach
                </x-select-input>
                <x-text-input name="title" placeholder="Subject" class="w-full" required />
                <x-textarea-input name="description" rows="3" class="w-full" placeholder="Describe the issue"></x-textarea-input>
                <x-primary-button class="w-full justify-center">Submit Ticket</x-primary-button>
            </form>
        </x-card>
    </div>
</x-app-layout>
