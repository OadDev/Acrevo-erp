@php
    $materialTotal = $workOrder->materialEntries->sum('amount');
    $labourTotal = $workOrder->labourEntries->sum('amount');
@endphp

<x-card class="mb-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h3 class="text-sm font-semibold text-gray-500">Total Budget</h3>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">₹{{ number_format($materialTotal + $labourTotal, 2) }}</p>
        </div>
        <dl class="flex gap-6 text-sm">
            <div><dt class="text-gray-400">Material Specifications</dt><dd class="font-medium text-gray-800 dark:text-gray-200">₹{{ number_format($materialTotal, 2) }}</dd></div>
            <div><dt class="text-gray-400">Man Power Schedule</dt><dd class="font-medium text-gray-800 dark:text-gray-200">₹{{ number_format($labourTotal, 2) }}</dd></div>
        </dl>
    </div>
</x-card>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <x-card :padded="false">
        <div class="flex items-center justify-between p-4">
            <h3 class="text-sm font-semibold text-gray-500">Using Material Specifications</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                        <th class="px-4 py-2">Material</th>
                        <th class="px-4 py-2">Brand / Size</th>
                        <th class="px-4 py-2">Qty</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($workOrder->materialEntries as $entry)
                        <tr>
                            <td class="px-4 py-2">{{ $entry->material_name }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ collect([$entry->brand, $entry->size])->filter()->join(' / ') ?: '—' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $entry->quantity }} {{ $entry->unit }}</td>
                            <td class="px-4 py-2 text-right font-medium">₹{{ number_format($entry->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No material entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @can('site_records.manage')
            <form method="POST" action="{{ route('work-orders.materials.store', $workOrder) }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800">
                @csrf
                <x-text-input name="material_name" placeholder="Material name" class="col-span-2 text-sm" required />
                <x-text-input name="brand" placeholder="Brand (optional)" class="text-sm" />
                <x-text-input name="size" placeholder="Size (optional)" class="text-sm" />
                <x-text-input name="unit" placeholder="Unit" value="Nos" class="text-sm" />
                <x-text-input type="number" step="0.01" name="quantity" placeholder="Qty" class="text-sm" required />
                <x-text-input type="number" step="0.01" name="rate" placeholder="Cost per unit" class="text-sm" required />
                <x-text-input name="vendor" placeholder="Vendor (optional)" class="text-sm" />
                <x-primary-button class="col-span-2 justify-center">Add Material Entry</x-primary-button>
            </form>
        @endcan
    </x-card>

    <x-card :padded="false">
        <div class="flex items-center justify-between p-4">
            <h3 class="text-sm font-semibold text-gray-500">Man Power Schedule Book</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <thead>
                    <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                        <th class="px-4 py-2">Designation</th>
                        <th class="px-4 py-2">Nos</th>
                        <th class="px-4 py-2">Target Hrs</th>
                        <th class="px-4 py-2">Total Time to Finish</th>
                        <th class="px-4 py-2">Remark</th>
                        <th class="px-4 py-2 text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($workOrder->labourEntries as $entry)
                        <tr>
                            <td class="px-4 py-2">{{ $entry->labour_type }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $entry->count }} × ₹{{ $entry->wage_rate }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $entry->hours ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $entry->total_time_to_finish ?? '—' }}</td>
                            <td class="px-4 py-2 text-gray-500">{{ $entry->remark ?? '—' }}</td>
                            <td class="px-4 py-2 text-right font-medium">₹{{ number_format($entry->amount, 2) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No labour entries yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @can('site_records.manage')
            <form method="POST" action="{{ route('work-orders.labour.store', $workOrder) }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800">
                @csrf
                <x-text-input name="labour_type" placeholder="Worker designation" class="col-span-2 text-sm" required />
                <x-text-input type="number" name="count" placeholder="Nos" value="1" class="text-sm" required />
                <x-text-input type="number" step="0.5" name="hours" placeholder="Target working hrs (optional)" class="text-sm" />
                <x-text-input type="number" step="0.01" name="wage_rate" placeholder="Salary" class="text-sm" required />
                <x-primary-button class="col-span-2 justify-center">Add Man Power Entry</x-primary-button>
            </form>
        @endcan
    </x-card>
</div>
