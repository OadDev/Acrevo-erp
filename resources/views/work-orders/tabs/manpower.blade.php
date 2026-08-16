@php
    $labourTotal = $workOrder->labourEntries->sum('amount');
@endphp

<x-card class="mb-6">
    <h3 class="text-sm font-semibold text-gray-500">Man Power Schedule Total</h3>
    <p class="text-2xl font-semibold text-gray-900 dark:text-white">₹{{ number_format($labourTotal, 2) }}</p>
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
        <form method="POST" action="{{ route('work-orders.labour.store', $workOrder) }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-4">
            @csrf
            <x-text-input name="labour_type" placeholder="Worker designation" class="col-span-2 text-sm" required />
            <x-text-input type="number" name="count" placeholder="Nos" value="1" class="text-sm" required />
            <x-text-input type="number" step="0.5" name="hours" placeholder="Target working hrs (optional)" class="text-sm" />
            <x-text-input type="number" step="0.01" name="wage_rate" placeholder="Salary" class="text-sm" required />
            <x-text-input name="total_time_to_finish" placeholder="Total time to finish (e.g. 2 days)" class="text-sm" />
            <x-text-input name="remark" placeholder="Remark (optional)" class="col-span-2 text-sm" />
            <x-primary-button class="col-span-2 justify-center sm:col-span-4">Add Man Power Entry</x-primary-button>
        </form>
    @endcan
</x-card>
