<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Verification History" subtitle="Every physical check logged against equipment and tools." />
    </x-slot>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('asset-verifications.index') }}" class="space-y-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by Asset ID, name, or serial number..." class="w-full rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-select-input name="result" class="text-sm">
                    <option value="">All Results</option>
                    @foreach (\App\Models\AssetVerification::RESULTS as $result)
                        <option value="{{ $result }}" @selected(request('result') === $result)>{{ ucwords(str_replace('_', ' ', $result)) }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="work_order_id" class="text-sm">
                    <option value="">Any Work Order</option>
                    @foreach ($workOrders as $wo)
                        <option value="{{ $wo->id }}" @selected(request('work_order_id') == $wo->id)>{{ $wo->work_order_no }}</option>
                    @endforeach
                </x-select-input>
                <x-text-input type="date" name="from" class="text-sm" value="{{ request('from') }}" placeholder="From" />
                <x-text-input type="date" name="to" class="text-sm" value="{{ request('to') }}" placeholder="To" />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-primary-button class="justify-center">Apply Filters</x-primary-button>
                @if (request()->hasAny(['q', 'result', 'work_order_id', 'from', 'to']))
                    <x-link-button :href="route('asset-verifications.index')" variant="secondary">Clear</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :padded="false">
        @if ($verifications->isEmpty())
            <div class="p-6"><x-empty-state icon="clipboard-check" title="No verifications found" description="Adjust your search/filters." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">Verified On</th>
                            <th class="px-4 py-3">Asset</th>
                            <th class="px-4 py-3">Work Order</th>
                            <th class="px-4 py-3">Result</th>
                            <th class="px-4 py-3">Condition</th>
                            <th class="px-4 py-3">Verified By</th>
                            <th class="px-4 py-3">Remarks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($verifications as $verification)
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('assets.show', $verification->asset) }}'">
                                <td class="px-4 py-3 text-gray-500">{{ $verification->verified_at->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $verification->asset->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $verification->asset->asset_code }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $verification->workOrder?->work_order_no ?? '—' }}</td>
                                <td class="px-4 py-3"><x-badge :status="$verification->result" /></td>
                                <td class="px-4 py-3 text-gray-500">{{ $verification->condition ?: '—' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $verification->verifiedBy?->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $verification->remarks ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $verifications->links() }}</div>
</x-app-layout>
