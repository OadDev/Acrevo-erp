<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <x-card>
        <h3 class="mb-4 text-sm font-semibold text-gray-500">Measurement Books</h3>
        @forelse ($workOrder->measurementBooks as $mb)
            <div class="border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                <p class="font-medium text-gray-800 dark:text-gray-200">{{ $mb->mb_no }} — {{ $mb->date->format('d M Y') }}</p>
                <p class="text-gray-500">{{ $mb->description }}</p>
                <p class="text-xs text-gray-400">{{ $mb->items->count() }} item(s) · ₹{{ number_format($mb->items->sum('amount'), 2) }}</p>
            </div>
        @empty
            <x-empty-state icon="file-text" title="No measurement books yet" />
        @endforelse

        @can('site_records.manage')
            <form method="POST" action="{{ route('work-orders.measurement-books.store', $workOrder) }}" class="mt-4 space-y-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                @csrf
                <x-textarea-input name="description" rows="2" class="w-full" placeholder="Description" required></x-textarea-input>
                <x-text-input type="date" name="date" class="w-full" value="{{ now()->format('Y-m-d') }}" required />
                <x-primary-button class="w-full justify-center">Add Measurement Book Entry</x-primary-button>
            </form>
        @endcan
    </x-card>

    <x-card :padded="false">
        <div class="p-4">
            <h3 class="text-sm font-semibold text-gray-500">Site Ledger</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($workOrder->ledgers as $entry)
                    <tr>
                        <td class="px-4 py-2">{{ $entry->entry_date->format('d M') }}</td>
                        <td class="px-4 py-2">{{ $entry->description }}</td>
                        <td class="px-4 py-2 text-right font-medium {{ $entry->type === 'credit' ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $entry->type === 'credit' ? '+' : '-' }}₹{{ number_format($entry->amount, 2) }}
                        </td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-center text-gray-400">No ledger entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @can('site_records.manage')
            <form method="POST" action="{{ route('work-orders.ledger.store', $workOrder) }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800">
                @csrf
                <x-select-input name="type" class="text-sm">
                    <option value="debit">Debit</option>
                    <option value="credit">Credit</option>
                </x-select-input>
                <x-text-input type="number" step="0.01" name="amount" placeholder="Amount" class="text-sm" required />
                <x-text-input name="category" placeholder="Category" class="col-span-2 text-sm" />
                <x-text-input name="description" placeholder="Description" class="col-span-2 text-sm" />
                <x-primary-button class="col-span-2 justify-center">Add Ledger Entry</x-primary-button>
            </form>
        @endcan
    </x-card>
</div>
