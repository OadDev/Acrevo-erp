<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Equipment History — '.$workOrder->work_order_no" subtitle="Every movement, repair, and missing incident that has touched this site's equipment.">
            <x-slot name="actions">
                <x-link-button :href="route('work-orders.equipment.history.pdf', array_merge(['workOrder' => $workOrder], request()->query()))" variant="secondary">Download PDF</x-link-button>
                <x-link-button :href="route('work-orders.equipment.index', $workOrder)" variant="secondary">Current Equipment</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('work-orders.equipment.history', $workOrder) }}" class="space-y-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by Asset ID or name..." class="w-full rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">
            <div class="flex flex-wrap items-center gap-2">
                <label class="text-xs text-gray-400">Date (received/reported/found)</label>
                <x-text-input type="date" name="from" class="text-sm" value="{{ request('from') }}" />
                <span class="text-xs text-gray-400">to</span>
                <x-text-input type="date" name="to" class="text-sm" value="{{ request('to') }}" />
                <x-primary-button class="justify-center">Apply Filters</x-primary-button>
                @if (request()->hasAny(['q', 'from', 'to']))
                    <x-link-button :href="route('work-orders.equipment.history', $workOrder)" variant="secondary">Clear</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :padded="false" class="mb-4">
        <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Movement History (assigned, transferred, returned)</h3></div>
        @if ($movements->isEmpty())
            <p class="px-4 pb-4 text-sm text-gray-400">No movements recorded for this site.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-2">Date</th>
                            <th class="px-4 py-2">Asset</th>
                            <th class="px-4 py-2">From</th>
                            <th class="px-4 py-2">To</th>
                            <th class="px-4 py-2">Type</th>
                            <th class="px-4 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($movements as $movement)
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('assets.show', $movement->asset) }}'">
                                <td class="px-4 py-2 text-gray-500">{{ $movement->moved_at->format('d M Y') }}</td>
                                <td class="px-4 py-2">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $movement->asset->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $movement->asset->asset_code }}</p>
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ $movement->locationLabel($movement->from_location, $movement->fromWorkOrder) }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $movement->locationLabel($movement->to_location, $movement->toWorkOrder) }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ ucwords(str_replace('_', ' ', $movement->type)) }}</td>
                                <td class="px-4 py-2"><x-badge :status="$movement->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $movements->links() }}</div>
        @endif
    </x-card>

    <x-card :padded="false" class="mb-4">
        <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Repairs Logged at This Site</h3></div>
        @if ($repairs->isEmpty())
            <p class="px-4 pb-4 text-sm text-gray-400">No repairs recorded for this site.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-2">Reported</th>
                            <th class="px-4 py-2">Asset</th>
                            <th class="px-4 py-2">Type</th>
                            <th class="px-4 py-2">Issue</th>
                            <th class="px-4 py-2">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($repairs as $repair)
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('assets.show', $repair->asset) }}'">
                                <td class="px-4 py-2 text-gray-500">{{ $repair->reported_date->format('d M Y') }}</td>
                                <td class="px-4 py-2">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $repair->asset->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $repair->asset->asset_code }}</p>
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ ucwords($repair->repair_type) }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $repair->issue_description }}</td>
                                <td class="px-4 py-2"><x-badge :status="$repair->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $repairs->links() }}</div>
        @endif
    </x-card>

    <x-card :padded="false">
        <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Missing Equipment Incidents at This Site</h3></div>
        @if ($missingLogs->isEmpty())
            <p class="px-4 pb-4 text-sm text-gray-400">No missing-equipment incidents recorded for this site.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-2">Date</th>
                            <th class="px-4 py-2">Asset</th>
                            <th class="px-4 py-2">Reported By</th>
                            <th class="px-4 py-2">Reason / Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($missingLogs as $log)
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('assets.show', $log->asset) }}'">
                                <td class="px-4 py-2 text-gray-500">{{ $log->created_at->format('d M Y, h:i A') }}</td>
                                <td class="px-4 py-2">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $log->asset->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $log->asset->asset_code }}</p>
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ $log->updatedBy?->name }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $log->reason ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="p-4">{{ $missingLogs->links() }}</div>
        @endif
    </x-card>
</x-app-layout>
