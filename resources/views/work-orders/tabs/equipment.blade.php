@php
    $assetOptions = \App\Models\Asset::orderBy('name')->get();
@endphp

@if (auth()->user()->hasRole('Admin'))
    <x-card class="mb-6" :padded="false">
        <div class="p-4">
            <h3 class="text-sm font-semibold text-gray-500">Assets</h3>
            <p class="mt-1 text-xs text-gray-400">Manage the predefined assets selectable when recording equipment allocated to a work order.</p>
        </div>
        <div class="flex flex-wrap gap-2 border-t border-gray-100 p-4 dark:border-gray-800">
            @forelse ($assetOptions as $asset)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    {{ $asset->name }}
                    <form method="POST" action="{{ route('admin.assets.destroy', $asset) }}" onsubmit="return confirm('Remove the asset &quot;{{ $asset->name }}&quot;? Existing equipment entries keep their asset name.')">
                        @csrf
                        @method('DELETE')
                        <button class="text-gray-400 hover:text-rose-500" title="Remove asset">&times;</button>
                    </form>
                </span>
            @empty
                <p class="text-xs text-gray-400">No assets yet - add one below.</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('admin.assets.store') }}" class="flex gap-2 border-t border-gray-100 p-4 dark:border-gray-800">
            @csrf
            <x-text-input name="name" placeholder="New asset name" class="text-sm" required />
            <x-primary-button class="whitespace-nowrap">Add Asset</x-primary-button>
        </form>
    </x-card>
@endif

<x-card :padded="false" x-data="{ editAsset: null }">
    <div class="flex items-center justify-between p-4">
        <h3 class="text-sm font-semibold text-gray-500">Equipment / Assets</h3>
        <a href="{{ route('work-orders.pdf.section', [$workOrder, 'equipment']) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
            <x-icon name="download" class="h-4 w-4" /> Download Report (PDF)
        </a>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                    <th class="px-4 py-2">Asset Name</th>
                    <th class="px-4 py-2 text-right">Total Allocated</th>
                    <th class="px-4 py-2 text-right">In Use</th>
                    <th class="px-4 py-2 text-right">Damaged</th>
                    <th class="px-4 py-2 text-right">Missing</th>
                    <th class="px-4 py-2">Remarks</th>
                    @if (auth()->user()->hasRole('Admin'))
                        <th class="px-4 py-2">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($workOrder->assets as $entry)
                    <tr>
                        <td class="px-4 py-2">{{ $entry->asset_name }}</td>
                        <td class="px-4 py-2 text-right text-gray-500">{{ $entry->allocated_quantity }}</td>
                        <td class="px-4 py-2 text-right text-emerald-600">{{ $entry->inUseQuantity() }}</td>
                        <td class="px-4 py-2 text-right text-amber-600">{{ $entry->damaged_quantity }}</td>
                        <td class="px-4 py-2 text-right text-rose-600">{{ $entry->missing_quantity }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->remarks ?? '—' }}</td>
                        @if (auth()->user()->hasRole('Admin'))
                            <td class="whitespace-nowrap px-4 py-2">
                                <button type="button" @click="editAsset === {{ $entry->id }} ? editAsset = null : editAsset = {{ $entry->id }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                                <form method="POST" action="{{ route('work-orders.equipment.destroy', [$workOrder, $entry]) }}" onsubmit="return confirm('Remove this asset entry?')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                    @if (auth()->user()->hasRole('Admin'))
                        <tr x-show="editAsset === {{ $entry->id }}" x-cloak>
                            <td colspan="7" class="bg-gray-50 px-4 py-3 dark:bg-gray-900">
                                <form method="POST" action="{{ route('work-orders.equipment.update', [$workOrder, $entry]) }}" class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                                    @csrf
                                    @method('PUT')
                                    <x-select-input name="asset_name" class="text-xs">
                                        @foreach ($assetOptions as $asset)
                                            <option value="{{ $asset->name }}" @selected($entry->asset_name === $asset->name)>{{ $asset->name }}</option>
                                        @endforeach
                                        @if ($assetOptions->doesntContain('name', $entry->asset_name))
                                            <option value="{{ $entry->asset_name }}" selected>{{ $entry->asset_name }}</option>
                                        @endif
                                    </x-select-input>
                                    <x-text-input type="number" name="allocated_quantity" value="{{ $entry->allocated_quantity }}" placeholder="Total" class="text-xs" required />
                                    <x-text-input type="number" name="damaged_quantity" value="{{ $entry->damaged_quantity }}" placeholder="Damaged" class="text-xs" />
                                    <x-text-input type="number" name="missing_quantity" value="{{ $entry->missing_quantity }}" placeholder="Missing" class="text-xs" />
                                    <x-text-input name="remarks" value="{{ $entry->remarks }}" placeholder="Remarks" class="text-xs" />
                                    <button class="col-span-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 sm:col-span-5">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No equipment entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @can('site_records.manage')
        <form method="POST" action="{{ route('work-orders.equipment.store', $workOrder) }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-5">
            @csrf
            <x-select-input name="asset_name" class="text-sm" required>
                <option value="">Select asset</option>
                @foreach ($assetOptions as $asset)
                    <option value="{{ $asset->name }}">{{ $asset->name }}</option>
                @endforeach
            </x-select-input>
            <x-text-input type="number" name="allocated_quantity" placeholder="Total Allocated" class="text-sm" required />
            <x-text-input type="number" name="damaged_quantity" placeholder="Damaged" class="text-sm" />
            <x-text-input type="number" name="missing_quantity" placeholder="Missing" class="text-sm" />
            <x-text-input name="remarks" placeholder="Remarks (optional)" class="text-sm" />
            <x-primary-button class="col-span-2 justify-center sm:col-span-5">Add Equipment Entry</x-primary-button>
        </form>
    @endcan
</x-card>
