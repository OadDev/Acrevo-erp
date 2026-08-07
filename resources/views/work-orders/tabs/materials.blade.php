<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <x-card :padded="false">
        <div class="flex items-center justify-between p-4">
            <h3 class="text-sm font-semibold text-gray-500">Material Entries</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($workOrder->materialEntries as $entry)
                    <tr>
                        <td class="px-4 py-2">{{ $entry->material_name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->quantity }} {{ $entry->unit }}</td>
                        <td class="px-4 py-2 text-right font-medium">₹{{ number_format($entry->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-center text-gray-400">No material entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @can('site_records.manage')
            <form method="POST" action="{{ route('work-orders.materials.store', $workOrder) }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800">
                @csrf
                <x-text-input name="material_name" placeholder="Material" class="col-span-2 text-sm" required />
                <x-text-input name="unit" placeholder="Unit" value="Nos" class="text-sm" />
                <x-text-input type="number" step="0.01" name="quantity" placeholder="Qty" class="text-sm" required />
                <x-text-input type="number" step="0.01" name="rate" placeholder="Rate" class="text-sm" required />
                <x-text-input name="vendor" placeholder="Vendor (optional)" class="text-sm" />
                <x-primary-button class="col-span-2 justify-center">Add Material Entry</x-primary-button>
            </form>
        @endcan
    </x-card>

    <x-card :padded="false">
        <div class="flex items-center justify-between p-4">
            <h3 class="text-sm font-semibold text-gray-500">Labour Entries</h3>
        </div>
        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($workOrder->labourEntries as $entry)
                    <tr>
                        <td class="px-4 py-2">{{ $entry->labour_type }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->count }} × ₹{{ $entry->wage_rate }}</td>
                        <td class="px-4 py-2 text-right font-medium">₹{{ number_format($entry->amount, 2) }}</td>
                    </tr>
                @empty
                    <tr><td class="px-4 py-6 text-center text-gray-400">No labour entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
        @can('site_records.manage')
            <form method="POST" action="{{ route('work-orders.labour.store', $workOrder) }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800">
                @csrf
                <x-text-input name="labour_type" placeholder="Labour type" class="col-span-2 text-sm" required />
                <x-text-input type="number" name="count" placeholder="Count" value="1" class="text-sm" required />
                <x-text-input type="number" step="0.01" name="wage_rate" placeholder="Wage rate" class="text-sm" required />
                <x-primary-button class="col-span-2 justify-center">Add Labour Entry</x-primary-button>
            </form>
        @endcan
    </x-card>
</div>
