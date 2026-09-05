<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Repair History" subtitle="Every repair logged against equipment and tools." />
    </x-slot>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('asset-repairs.index') }}" class="space-y-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by Asset ID, name, or serial number..." class="w-full rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <x-select-input name="status" class="text-sm">
                    <option value="">All Statuses</option>
                    @foreach (\App\Models\AssetRepair::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="repair_type" class="text-sm">
                    <option value="">All Repair Types</option>
                    @foreach (\App\Models\AssetRepair::TYPES as $type)
                        <option value="{{ $type }}" @selected(request('repair_type') === $type)>{{ ucwords($type) }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="warranty" class="text-sm">
                    <option value="">Warranty or Paid</option>
                    <option value="1" @selected(request('warranty') === '1')>Warranty Repair</option>
                    <option value="0" @selected(request('warranty') === '0')>Paid Repair</option>
                </x-select-input>
                <x-text-input name="technician_vendor" class="text-sm" value="{{ request('technician_vendor') }}" placeholder="Technician / Vendor" />
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
                <label class="ml-2 text-xs text-gray-400">Cost</label>
                <x-text-input type="number" step="0.01" name="cost_min" class="w-24 text-sm" value="{{ request('cost_min') }}" placeholder="Min" />
                <x-text-input type="number" step="0.01" name="cost_max" class="w-24 text-sm" value="{{ request('cost_max') }}" placeholder="Max" />
                <x-primary-button class="justify-center">Apply Filters</x-primary-button>
                @if (request()->hasAny(['q', 'status', 'repair_type', 'warranty', 'technician_vendor', 'work_order_id', 'from', 'to', 'cost_min', 'cost_max']))
                    <x-link-button :href="route('asset-repairs.index')" variant="secondary">Clear</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :padded="false">
        @if ($repairs->isEmpty())
            <div class="p-6"><x-empty-state icon="wrench" title="No repairs found" description="Adjust your search/filters." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">Reported</th>
                            <th class="px-4 py-3">Asset</th>
                            <th class="px-4 py-3">Type</th>
                            <th class="px-4 py-3">Technician / Vendor</th>
                            <th class="px-4 py-3">Warranty</th>
                            <th class="px-4 py-3 text-right">Cost</th>
                            <th class="px-4 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($repairs as $repair)
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('assets.show', $repair->asset) }}'">
                                <td class="px-4 py-3 text-gray-500">{{ $repair->reported_date->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $repair->asset->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $repair->asset->asset_code }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ ucwords($repair->repair_type) }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $repair->technician_vendor ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $repair->is_warranty_repair ? 'Warranty' : 'Paid' }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ $repair->cost !== null ? 'Rs. '.number_format($repair->cost, 2) : '—' }}</td>
                                <td class="px-4 py-3"><x-badge :status="$repair->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $repairs->links() }}</div>
</x-app-layout>
