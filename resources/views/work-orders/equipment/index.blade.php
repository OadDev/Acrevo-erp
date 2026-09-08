<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Equipment — '.$workOrder->work_order_no" subtitle="Current equipment assigned to this site.">
            <x-slot name="actions">
                <x-link-button :href="route('work-orders.equipment.pdf', array_merge(['workOrder' => $workOrder], request()->query()))" variant="secondary">Download PDF</x-link-button>
                @can('assets.view_history')
                    <x-link-button :href="route('work-orders.equipment.history', $workOrder)" variant="secondary">Equipment History</x-link-button>
                @endcan
                <x-link-button :href="route('work-orders.show', $workOrder)" variant="secondary">Back to Work Order</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    @if ($pendingMovements->isNotEmpty())
        <x-card :padded="false" class="mb-4">
            <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Waiting for Confirmation</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-2">Asset</th>
                            <th class="px-4 py-2">From</th>
                            <th class="px-4 py-2 text-right">Qty</th>
                            <th class="px-4 py-2">Sent</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($pendingMovements as $movement)
                            <tr>
                                <td class="px-4 py-2">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $movement->asset->name ?? 'Removed Asset' }}</p>
                                    <p class="text-xs text-gray-400">{{ $movement->asset->asset_code ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ $movement->locationLabel($movement->from_location, $movement->fromWorkOrder) }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ $movement->quantity }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $movement->moved_at->format('d M Y') }}</td>
                                <td class="px-4 py-2"><x-badge status="waiting_for_confirmation" /></td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    @if ($canConfirmHere)
                                        <form method="POST" action="{{ route('asset-movements.confirm', $movement) }}" class="inline">
                                            @csrf
                                            <button class="text-xs font-medium text-emerald-600 hover:underline">Confirm</button>
                                        </form>
                                        <form method="POST" action="{{ route('asset-movements.cancel', $movement) }}" class="inline" onsubmit="return confirm('Cancel this movement?')">
                                            @csrf
                                            <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Cancel</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif

    <x-card class="mb-4">
        <form method="GET" action="{{ route('work-orders.equipment.index', $workOrder) }}" class="space-y-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by Asset ID, name, serial number, or category..." class="w-full rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-select-input name="status" class="text-sm">
                    <option value="">All Statuses</option>
                    @foreach (\App\Models\Asset::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="direction" class="text-sm">
                    <option value="asc" @selected(request('direction', 'asc') === 'asc')>Name A-Z</option>
                    <option value="desc" @selected(request('direction') === 'desc')>Name Z-A</option>
                </x-select-input>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-primary-button class="justify-center">Apply Filters</x-primary-button>
                @if (request()->hasAny(['q', 'status', 'direction']))
                    <x-link-button :href="route('work-orders.equipment.index', $workOrder)" variant="secondary">Clear</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :padded="false">
        @if ($assets->isEmpty())
            <div class="p-6"><x-empty-state icon="wrench" title="No equipment at this site" description="Nothing is currently assigned here." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">Asset</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Serial Number</th>
                            <th class="px-4 py-3 text-right">Qty Here</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Condition</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($assets as $asset)
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('assets.show', $asset) }}'">
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $asset->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $asset->asset_code }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $asset->category ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $asset->serial_number ?: '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ $asset->stocks->sum('quantity') }}</td>
                                <td class="px-4 py-3"><x-badge :status="$asset->status" /></td>
                                <td class="px-4 py-3 text-gray-500">{{ $asset->condition ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $assets->links() }}</div>
</x-app-layout>
