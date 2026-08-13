<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Work Orders" subtitle="The single source of truth for every active project.">
            <x-slot name="actions">
                <x-select-input name="status" class="text-sm" onchange="location.href='{{ route('work-orders.index') }}?status='+this.value">
                    <option value="">All Statuses</option>
                    @foreach (\App\Models\WorkOrder::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::title(str_replace('_',' ',$status)) }}</option>
                    @endforeach
                </x-select-input>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card :padded="false">
        @if ($workOrders->isEmpty())
            <div class="p-6">
                <x-empty-state icon="clipboard" title="No active work orders" description="Generate one from an approved quotation." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Site ID</th>
                            <th class="px-5 py-3">Work Order</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Executive Team</th>
                            <th class="px-5 py-3">Priority</th>
                            <th class="px-5 py-3">Deadline</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($workOrders as $workOrder)
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('work-orders.show', $workOrder) }}'">
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    @if ($workOrder->site)
                                        <a href="{{ route('sites.show', $workOrder->site) }}" onclick="event.stopPropagation()" class="text-indigo-600 hover:underline">{{ $workOrder->site->site_no }}</a>
                                    @else
                                        —
                                    @endif
                                </td>
                                <td class="px-5 py-3">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $workOrder->work_order_no }}</span>
                                    <p class="text-xs text-gray-400">{{ $workOrder->title }}</p>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $workOrder->client?->name ?? 'Unknown client' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $workOrder->executiveTeams->whereNull('unassigned_at')->pluck('executiveTeam.name')->join(', ') ?: '—' }}
                                </td>
                                <td class="px-5 py-3"><x-badge :status="$workOrder->priority" /></td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ optional($workOrder->deadline)->format('d M Y') ?? '—' }}</td>
                                <td class="px-5 py-3"><x-badge :status="$workOrder->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $workOrders->links() }}</div>
</x-app-layout>
