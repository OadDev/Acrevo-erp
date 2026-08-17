@php
    $materialAllocated = (float) ($workOrder->estimated_material_budget ?? 0);
    $materialTotal = $workOrder->materialEntries->sum('amount');
    $materialRemaining = $materialAllocated - $materialTotal;
@endphp

<x-card class="mb-6">
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div>
            <h3 class="text-sm font-semibold text-gray-500">Allocated Material Budget</h3>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">₹{{ number_format($materialAllocated, 2) }}</p>
            <p class="text-xs text-gray-400">Set on the work order's create page.</p>
        </div>
        <div>
            <h3 class="text-sm font-semibold text-gray-500">Material Inward Total</h3>
            <p class="text-2xl font-semibold text-gray-900 dark:text-white">₹{{ number_format($materialTotal, 2) }}</p>
            <p class="text-xs text-gray-400">Actual site purchases recorded below.</p>
        </div>
        <div>
            <h3 class="text-sm font-semibold text-gray-500">Remaining Budget</h3>
            <p class="text-2xl font-semibold {{ $materialRemaining < 0 ? 'text-rose-600' : 'text-gray-900 dark:text-white' }}">₹{{ number_format($materialRemaining, 2) }}</p>
            @if ($materialRemaining < 0)
                <p class="text-xs text-rose-500">Over allocated budget.</p>
            @endif
        </div>
    </div>
</x-card>

<x-card class="mb-6" :padded="false" x-data="{ editMaterial: null }">
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
                    @if (auth()->user()->hasRole('Admin'))
                        <th class="px-4 py-2">Actions</th>
                    @endif
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
                        @if (auth()->user()->hasRole('Admin'))
                            <td class="whitespace-nowrap px-4 py-2">
                                <button type="button" @click="editMaterial === {{ $entry->id }} ? editMaterial = null : editMaterial = {{ $entry->id }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                                <form method="POST" action="{{ route('work-orders.materials.destroy', [$workOrder, $entry]) }}" onsubmit="return confirm('Remove this material inward entry?')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                    @if (auth()->user()->hasRole('Admin'))
                        <tr x-show="editMaterial === {{ $entry->id }}" x-cloak>
                            <td colspan="10" class="bg-gray-50 px-4 py-3 dark:bg-gray-900">
                                <form method="POST" action="{{ route('work-orders.materials.update', [$workOrder, $entry]) }}" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                                    @csrf
                                    @method('PUT')
                                    <x-text-input type="date" name="entry_date" value="{{ $entry->entry_date?->format('Y-m-d') }}" class="text-xs" required />
                                    <x-text-input name="material_name" value="{{ $entry->material_name }}" class="col-span-2 text-xs sm:col-span-2" required />
                                    <x-text-input type="number" step="0.01" name="quantity" value="{{ $entry->quantity }}" class="text-xs" required />
                                    <x-text-input name="unit" value="{{ $entry->unit }}" class="text-xs" required />
                                    <x-text-input type="number" step="0.01" name="rate" value="{{ $entry->rate }}" class="text-xs" required />
                                    <x-select-input name="scope" class="text-xs">
                                        <option value="">Scope (optional)</option>
                                        <option value="client" @selected($entry->scope === 'client')>Client</option>
                                        <option value="company" @selected($entry->scope === 'company')>Company</option>
                                    </x-select-input>
                                    <x-text-input name="vendor" value="{{ $entry->vendor }}" class="text-xs" />
                                    <x-text-input name="delivery_vehicle_details" value="{{ $entry->delivery_vehicle_details }}" class="text-xs" />
                                    <button class="col-span-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 sm:col-span-3">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="10" class="px-4 py-6 text-center text-gray-400">No material inward entries yet.</td></tr>
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

<x-card :padded="false" x-data="{ editUsage: null }">
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
                    @if (auth()->user()->hasRole('Admin'))
                        <th class="px-4 py-2">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($workOrder->materialUsageEntries as $entry)
                    <tr>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->date->format('d M Y') }}</td>
                        <td class="px-4 py-2">{{ $entry->material_name }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->quantity }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->unit }}</td>
                        @if (auth()->user()->hasRole('Admin'))
                            <td class="whitespace-nowrap px-4 py-2">
                                <button type="button" @click="editUsage === {{ $entry->id }} ? editUsage = null : editUsage = {{ $entry->id }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                                <form method="POST" action="{{ route('work-orders.material-usage.destroy', [$workOrder, $entry]) }}" onsubmit="return confirm('Remove this daily usage entry?')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                    @if (auth()->user()->hasRole('Admin'))
                        <tr x-show="editUsage === {{ $entry->id }}" x-cloak>
                            <td colspan="5" class="bg-gray-50 px-4 py-3 dark:bg-gray-900">
                                <form method="POST" action="{{ route('work-orders.material-usage.update', [$workOrder, $entry]) }}" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    @csrf
                                    @method('PUT')
                                    <x-text-input type="date" name="date" value="{{ $entry->date->format('Y-m-d') }}" class="text-xs" required />
                                    <x-text-input name="material_name" value="{{ $entry->material_name }}" class="text-xs" required />
                                    <x-text-input type="number" step="0.01" name="quantity" value="{{ $entry->quantity }}" class="text-xs" required />
                                    <x-text-input name="unit" value="{{ $entry->unit }}" class="text-xs" required />
                                    <button class="col-span-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 sm:col-span-4">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No daily usage entries yet.</td></tr>
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
