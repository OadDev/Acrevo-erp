<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Items" subtitle="Reusable catalog for autofilling Proforma Invoice, Tax Invoice, and Delivery Challan line items." />
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card :padded="false" class="lg:col-span-2" x-data="{ editing: null }">
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">HSN/SAC</th>
                        <th class="px-4 py-3">Unit</th>
                        <th class="px-4 py-3 text-right">Default Rate</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($items as $item)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $item->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $item->hsn_sac_code ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $item->unit }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">{{ $item->default_rate !== null ? number_format($item->default_rate, 2) : '—' }}</td>
                            <td class="px-4 py-3"><x-badge :status="$item->is_active ? 'active' : 'inactive'" /></td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click="editing === {{ $item->id }} ? editing = null : editing = {{ $item->id }}" class="text-sm text-indigo-600 hover:underline">Edit</button>
                                <form method="POST" action="{{ route('admin.items.destroy', $item) }}" class="inline" onsubmit="return confirm('Remove this item?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ml-3 text-sm text-rose-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <tr x-show="editing === {{ $item->id }}" x-cloak>
                            <td colspan="6" class="bg-gray-50 px-4 py-3 dark:bg-gray-900">
                                <form method="POST" action="{{ route('admin.items.update', $item) }}" class="grid grid-cols-2 gap-2 sm:grid-cols-6">
                                    @csrf
                                    @method('PUT')
                                    <x-text-input name="name" value="{{ $item->name }}" class="text-sm" required />
                                    <x-text-input name="hsn_sac_code" value="{{ $item->hsn_sac_code }}" placeholder="HSN/SAC" class="text-sm" />
                                    <x-text-input name="unit" value="{{ $item->unit }}" class="text-sm" required />
                                    <x-text-input type="number" step="0.01" min="0" name="default_rate" value="{{ $item->default_rate }}" placeholder="Rate" class="text-sm" />
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="is_active" value="1" @checked($item->is_active) class="rounded border-gray-300 text-indigo-600">
                                        Active
                                    </label>
                                    <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No items yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Add Item</h3>
            <form method="POST" action="{{ route('admin.items.store') }}" class="space-y-2">
                @csrf
                <x-text-input name="name" placeholder="Item Name" class="w-full text-sm" required />
                <x-text-input name="hsn_sac_code" placeholder="HSN/SAC Code" class="w-full text-sm" />
                <x-text-input name="unit" placeholder="Unit (e.g. Nos)" class="w-full text-sm" value="Nos" required />
                <x-text-input type="number" step="0.01" min="0" name="default_rate" placeholder="Default Rate (optional)" class="w-full text-sm" />
                <x-primary-button class="w-full justify-center">Add Item</x-primary-button>
            </form>
        </x-card>
    </div>
</x-app-layout>
