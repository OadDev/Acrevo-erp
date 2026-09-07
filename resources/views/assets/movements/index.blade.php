<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Movement History" subtitle="Every asset movement across the company store and every site." />
    </x-slot>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('asset-movements.index') }}" class="space-y-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by Asset ID, name, serial number, or work order..." class="w-full rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <x-select-input name="type" class="text-sm">
                    <option value="">All Types</option>
                    @foreach (\App\Models\AssetMovement::TYPES as $type)
                        <option value="{{ $type }}" @selected(request('type') === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="status" class="text-sm">
                    <option value="">All Statuses</option>
                    @foreach (\App\Models\AssetMovement::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords($status) }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="from_location" class="text-sm">
                    <option value="">From: Any Location</option>
                    @foreach (\App\Models\AssetMovement::LOCATIONS as $location)
                        <option value="{{ $location }}" @selected(request('from_location') === $location)>{{ ucwords(str_replace('_', ' ', $location)) }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="to_location" class="text-sm">
                    <option value="">To: Any Location</option>
                    @foreach (\App\Models\AssetMovement::LOCATIONS as $location)
                        <option value="{{ $location }}" @selected(request('to_location') === $location)>{{ ucwords(str_replace('_', ' ', $location)) }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="work_order_id" class="text-sm">
                    <option value="">Any Work Order</option>
                    @foreach ($workOrders as $wo)
                        <option value="{{ $wo->id }}" @selected(request('work_order_id') == $wo->id)>{{ $wo->work_order_no }}</option>
                    @endforeach
                </x-select-input>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <label class="text-xs text-gray-400">Date</label>
                <x-text-input type="date" name="from" class="text-sm" value="{{ request('from') }}" />
                <span class="text-xs text-gray-400">to</span>
                <x-text-input type="date" name="to" class="text-sm" value="{{ request('to') }}" />
                <x-primary-button class="justify-center">Apply Filters</x-primary-button>
                @if (request()->hasAny(['q', 'type', 'status', 'from_location', 'to_location', 'work_order_id', 'from', 'to']))
                    <x-link-button :href="route('asset-movements.index')" variant="secondary">Clear</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :padded="false">
        @if ($movements->isEmpty())
            <div class="p-6"><x-empty-state icon="history" title="No movements found" description="Adjust your search/filters." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3">Asset</th>
                            <th class="px-4 py-3">From</th>
                            <th class="px-4 py-3">To</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Created By</th>
                            @canany(['movements.edit', 'movements.delete'])
                                <th class="px-4 py-3"></th>
                            @endcanany
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($movements as $movement)
                            <tr>
                                <td class="px-4 py-3 text-gray-500 @if ($movement->asset) cursor-pointer @endif" @if ($movement->asset) onclick="window.location='{{ route('assets.show', $movement->asset) }}'" @endif>{{ $movement->moved_at->format('d M Y') }}</td>
                                <td class="px-4 py-3 @if ($movement->asset) cursor-pointer @endif" @if ($movement->asset) onclick="window.location='{{ route('assets.show', $movement->asset) }}'" @endif>
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $movement->asset->name ?? 'Removed Asset' }}</p>
                                    <p class="text-xs text-gray-400">{{ $movement->asset->asset_code ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $movement->locationLabel($movement->from_location, $movement->fromWorkOrder) }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $movement->locationLabel($movement->to_location, $movement->toWorkOrder) }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ ucwords(str_replace('_', ' ', $movement->type)) }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ $movement->quantity }}</td>
                                <td class="px-4 py-3"><x-badge :status="$movement->status" /></td>
                                <td class="px-4 py-3 text-gray-500">{{ $movement->createdBy?->name }}</td>
                                @canany(['movements.edit', 'movements.delete'])
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        @can('movements.edit')
                                            @if ($movement->status === 'pending')
                                                <a href="{{ route('asset-movements.edit', $movement) }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</a>
                                            @endif
                                        @endcan
                                        @can('movements.delete')
                                            <form method="POST" action="{{ route('asset-movements.destroy', $movement) }}" class="inline" onsubmit="return confirm('Remove this movement entry? This cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Remove</button>
                                            </form>
                                        @endcan
                                    </td>
                                @endcanany
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $movements->links() }}</div>
</x-app-layout>
