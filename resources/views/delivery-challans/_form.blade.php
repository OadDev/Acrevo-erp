@php
    $dc = $deliveryChallan ?? null;
@endphp
<div
    x-data="challanBuilder({
        items: {{ $dc && $dc->items->isNotEmpty() ? $dc->items->map(fn ($i) => ['item_id' => $i->item_id, 'name' => $i->name, 'hsn_sac_code' => $i->hsn_sac_code, 'unit' => $i->unit, 'quantity' => (float) $i->quantity])->toJson() : '[{"item_id":null,"name":"","hsn_sac_code":"","unit":"Nos","quantity":1}]' }},
        catalog: {{ $items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'hsn_sac_code' => $i->hsn_sac_code, 'unit' => $i->unit])->toJson() }},
    })"
>
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Document Details</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label value="Company" />
                    <x-select-input name="company_id" required class="mt-1 block w-full">
                        <option value="">Select company...</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('company_id', $dc->company_id ?? '') == $company->id)>{{ $company->name }} ({{ $company->code }})</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Party (Client)" />
                    <x-select-input name="client_id" required class="mt-1 block w-full">
                        <option value="">Select client...</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id', $dc->client_id ?? '') == $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Challan Date" />
                    <x-text-input type="date" name="challan_date" required class="mt-1 block w-full" value="{{ old('challan_date', optional($dc->challan_date ?? null)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" />
                </div>
                <div>
                    <x-input-label value="Delivery Time" />
                    <x-text-input name="delivery_time" placeholder="e.g. 10:00 AM" class="mt-1 block w-full" value="{{ old('delivery_time', $dc->delivery_time ?? '') }}" />
                </div>
                @if ($dc)
                    <div>
                        <x-input-label value="Status" />
                        <x-select-input name="status" class="mt-1 block w-full">
                            @foreach (\App\Models\DeliveryChallan::STATUSES as $status)
                                <option value="{{ $status }}" @selected($dc->status === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                @endif
            </div>
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Shipping To (if different from Party)</h3>
            <div class="space-y-3">
                <x-text-input name="shipping_name" placeholder="Shipping Name" class="w-full text-sm" value="{{ old('shipping_name', $dc->shipping_name ?? '') }}" />
                <x-textarea-input name="shipping_address" rows="2" placeholder="Shipping Address" class="w-full text-sm">{{ old('shipping_address', $dc->shipping_address ?? '') }}</x-textarea-input>
                <div class="grid grid-cols-2 gap-3">
                    <x-text-input name="shipping_phone" placeholder="Phone" class="w-full text-sm" value="{{ old('shipping_phone', $dc->shipping_phone ?? '') }}" />
                    <x-text-input name="shipping_email" placeholder="Email" class="w-full text-sm" value="{{ old('shipping_email', $dc->shipping_email ?? '') }}" />
                </div>
                <x-text-input name="shipping_tax_id" placeholder="GSTIN / VATIN" class="w-full text-sm" value="{{ old('shipping_tax_id', $dc->shipping_tax_id ?? '') }}" />
            </div>
        </x-card>
    </div>

    <x-card :padded="false" class="mt-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Catalog Item</th>
                        <th class="px-4 py-3">Item Name</th>
                        <th class="px-4 py-3 w-28">HSN/SAC</th>
                        <th class="px-4 py-3 w-24">Quantity</th>
                        <th class="px-4 py-3 w-24">Unit</th>
                        <th class="px-2 py-3 w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(item, index) in items" :key="index">
                        <tr class="border-b border-gray-100 dark:border-gray-800">
                            <td class="px-4 py-2">
                                <select @change="applyCatalogItem(item, $event.target.value)" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                                    <option value="">Type manually...</option>
                                    <template x-for="c in catalog" :key="c.id">
                                        <option :value="c.id" x-text="c.name"></option>
                                    </template>
                                </select>
                            </td>
                            <td class="px-4 py-2">
                                <input type="text" :name="'items['+index+'][name]'" x-model="item.name" placeholder="Item name" required class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                                <input type="hidden" :name="'items['+index+'][item_id]'" x-model="item.item_id">
                            </td>
                            <td class="px-4 py-2">
                                <input type="text" :name="'items['+index+'][hsn_sac_code]'" x-model="item.hsn_sac_code" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" step="0.01" min="0.01" :name="'items['+index+'][quantity]'" x-model.number="item.quantity" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                            </td>
                            <td class="px-4 py-2">
                                <input type="text" :name="'items['+index+'][unit]'" x-model="item.unit" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                            </td>
                            <td class="px-2 py-2 text-center">
                                <button type="button" @click="removeItem(index)" class="text-gray-400 hover:text-rose-500">
                                    <x-icon name="trash" class="h-4 w-4" />
                                </button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 p-4 dark:border-gray-800">
            <button type="button" @click="addItem" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                <x-icon name="plus" class="h-4 w-4" /> Add Line Item
            </button>
        </div>
    </x-card>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Terms &amp; Conditions</h3>
            <x-textarea-input name="terms_and_conditions" rows="4" class="w-full text-sm">{{ old('terms_and_conditions', $dc->terms_and_conditions ?? '') }}</x-textarea-input>
        </x-card>
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Received By</h3>
            <div class="space-y-3">
                <x-text-input name="received_by_name" placeholder="Name" class="w-full text-sm" value="{{ old('received_by_name', $dc->received_by_name ?? '') }}" />
                <x-textarea-input name="received_by_comment" rows="2" placeholder="Comment" class="w-full text-sm">{{ old('received_by_comment', $dc->received_by_comment ?? '') }}</x-textarea-input>
                <x-text-input type="date" name="received_by_date" class="w-full text-sm" value="{{ old('received_by_date', optional($dc->received_by_date ?? null)->format('Y-m-d')) }}" />
            </div>
        </x-card>
        <x-card class="lg:col-start-2">
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Delivered By</h3>
            <div class="space-y-3">
                <x-text-input name="delivered_by_name" placeholder="Name" class="w-full text-sm" value="{{ old('delivered_by_name', $dc->delivered_by_name ?? '') }}" />
                <x-textarea-input name="delivered_by_comment" rows="2" placeholder="Comment" class="w-full text-sm">{{ old('delivered_by_comment', $dc->delivered_by_comment ?? '') }}</x-textarea-input>
                <x-text-input type="date" name="delivered_by_date" class="w-full text-sm" value="{{ old('delivered_by_date', optional($dc->delivered_by_date ?? null)->format('Y-m-d')) }}" />
            </div>
        </x-card>
    </div>
</div>
