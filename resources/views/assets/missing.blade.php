<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Missing Equipment" subtitle="Every asset currently marked missing, company-wide.">
            <x-slot name="actions">
                <x-link-button :href="route('assets.missing.pdf', request()->query())" variant="secondary">Download PDF</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('assets.missing') }}" class="space-y-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by Asset ID, name, serial number, or category..." class="w-full rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-select-input name="work_order_id" class="text-sm">
                    <option value="">Last Known Work Order</option>
                    @foreach ($workOrders as $wo)
                        <option value="{{ $wo->id }}" @selected(request('work_order_id') == $wo->id)>{{ $wo->work_order_no }}</option>
                    @endforeach
                </x-select-input>
                <x-text-input type="date" name="from" class="text-sm" value="{{ request('from') }}" placeholder="Reported From" />
                <x-text-input type="date" name="to" class="text-sm" value="{{ request('to') }}" placeholder="Reported To" />
                <x-select-input name="direction" class="text-sm">
                    <option value="asc" @selected(request('direction', 'asc') === 'asc')>Name A-Z</option>
                    <option value="desc" @selected(request('direction') === 'desc')>Name Z-A</option>
                </x-select-input>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-primary-button class="justify-center">Apply Filters</x-primary-button>
                @if (request()->hasAny(['q', 'work_order_id', 'from', 'to', 'direction']))
                    <x-link-button :href="route('assets.missing')" variant="secondary">Clear</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :padded="false">
        @if ($assets->isEmpty())
            <div class="p-6"><x-empty-state icon="search" title="No missing equipment" description="Nothing is currently marked missing." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">Asset</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Last Known Site / WO</th>
                            <th class="px-4 py-3">Reported By</th>
                            <th class="px-4 py-3">Reported Date</th>
                            <th class="px-4 py-3">Days Missing</th>
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
                                <td class="px-4 py-3 text-gray-500">{{ $asset->currentWorkOrder?->work_order_no ?? ucwords(str_replace('_', ' ', $asset->current_location)) }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $asset->missingSince?->updatedBy?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $asset->missingSince?->created_at?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $asset->missingSince ? $asset->missingSince->created_at->diffInDays(now()) : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $assets->links() }}</div>
</x-app-layout>
