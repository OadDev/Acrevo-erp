@php
    $materialTotal = $workOrder->materialEntries->sum('amount');
@endphp

<x-card class="mb-6">
    <h3 class="text-sm font-semibold text-gray-500">Material Inward Total</h3>
    <p class="text-2xl font-semibold text-gray-900 dark:text-white">₹{{ number_format($materialTotal, 2) }}</p>
</x-card>

<x-card class="mb-6" :padded="false">
    <div class="flex items-center justify-between p-4">
        <h3 class="text-sm font-semibold text-gray-500">Material Inward</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Material Description</th>
                    <th class="px-4 py-2">Nos</th>
                    <th class="px-4 py-2">Unit</th>
                    <th class="px-4 py-2 text-right">Rate/Unit</th>
                    <th class="px-4 py-2 text-right">Total Rate</th>
                    <th class="px-4 py-2">Scope</th>
                    <th class="px-4 py-2">Supplier Details</th>
                    <th class="px-4 py-2">Delivery Vehicle Details</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($workOrder->materialEntries as $entry)
                    <tr>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->entry_date?->format('d M Y') ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $entry->material_name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->quantity }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->unit }}</td>
                        <td class="px-4 py-2 text-right text-gray-500">₹{{ number_format($entry->rate, 2) }}</td>
                        <td class="px-4 py-2 text-right font-medium">₹{{ number_format($entry->amount, 2) }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->scope ? ucfirst($entry->scope) : '—' }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->vendor ?? '—' }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->delivery_vehicle_details ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-6 text-center text-gray-400">No material inward entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @can('site_records.manage')
        <form method="POST" action="{{ route('work-orders.materials.store', $workOrder) }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-3">
            @csrf
            <x-text-input type="date" name="entry_date" value="{{ now()->format('Y-m-d') }}" class="text-sm" required />
            <x-text-input name="material_name" placeholder="Material description" class="col-span-2 text-sm sm:col-span-2" required />
            <x-text-input type="number" step="0.01" name="quantity" placeholder="Nos" class="text-sm" required />
            <x-text-input name="unit" placeholder="Unit" class="text-sm" required />
            <x-text-input type="number" step="0.01" name="rate" placeholder="Rate per unit" class="text-sm" required />
            <x-select-input name="scope" class="text-sm">
                <option value="">Scope (optional)</option>
                <option value="client">Client</option>
                <option value="company">Company</option>
            </x-select-input>
            <x-text-input name="vendor" placeholder="Supplier details (optional)" class="text-sm" />
            <x-text-input name="delivery_vehicle_details" placeholder="Delivery vehicle details (optional)" class="text-sm" />
            <x-primary-button class="col-span-2 justify-center sm:col-span-3">Add Material Inward</x-primary-button>
        </form>
    @endcan
</x-card>

<x-card :padded="false">
    <div class="flex items-center justify-between p-4">
        <h3 class="text-sm font-semibold text-gray-500">Daily Material Used Entry</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Material Name</th>
                    <th class="px-4 py-2">Nos</th>
                    <th class="px-4 py-2">Unit</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($workOrder->materialUsageEntries as $entry)
                    <tr>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->date->format('d M Y') }}</td>
                        <td class="px-4 py-2">{{ $entry->material_name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->quantity }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->unit }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No daily usage entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @can('site_records.manage')
        <form method="POST" action="{{ route('work-orders.material-usage.store', $workOrder) }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-4">
            @csrf
            <x-text-input type="date" name="date" value="{{ now()->format('Y-m-d') }}" class="text-sm" required />
            <x-text-input name="material_name" placeholder="Material name (e.g. Cement, Sand)" class="text-sm" required />
            <x-text-input type="number" step="0.01" name="quantity" placeholder="Nos" class="text-sm" required />
            <x-text-input name="unit" placeholder="Unit (Bag, Cft...)" class="text-sm" required />
            <x-primary-button class="col-span-2 justify-center sm:col-span-4">Add Daily Usage Entry</x-primary-button>
        </form>
    @endcan
</x-card>
