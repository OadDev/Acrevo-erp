@php
    $ti = $taxInvoice ?? null;
@endphp
<div
    x-data="billingBuilder({
        items: {{ $ti && $ti->items->isNotEmpty() ? $ti->items->map(fn ($i) => ['item_id' => $i->item_id, 'name' => $i->name, 'hsn_sac_code' => $i->hsn_sac_code, 'unit' => $i->unit, 'quantity' => (float) $i->quantity, 'rate' => (float) $i->rate])->toJson() : '[{"item_id":null,"name":"","hsn_sac_code":"","unit":"Nos","quantity":1,"rate":0}]' }},
        taxPercent: {{ $ti->tax_percent ?? 0 }},
        catalog: {{ $items->map(fn ($i) => ['id' => $i->id, 'name' => $i->name, 'hsn_sac_code' => $i->hsn_sac_code, 'unit' => $i->unit, 'rate' => (float) ($i->default_rate ?? 0)])->toJson() }},
    })"
>
    @if ($ti?->proformaInvoice)
        <div class="mb-4 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400">
            Created by converting Proforma Invoice <a href="{{ route('proforma-invoices.show', $ti->proformaInvoice) }}" class="font-semibold hover:underline">{{ $ti->proformaInvoice->proforma_no }}</a>.
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Document Details</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label value="Company" />
                    <x-select-input name="company_id" required class="mt-1 block w-full">
                        <option value="">Select company...</option>
                        @foreach ($companies as $company)
                            <option value="{{ $company->id }}" @selected(old('company_id', $ti->company_id ?? '') == $company->id)>{{ $company->name }} ({{ $company->code }})</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Client (Buyer)" />
                    <x-select-input name="client_id" required class="mt-1 block w-full">
                        <option value="">Select client...</option>
                        @foreach ($clients as $client)
                            <option value="{{ $client->id }}" @selected(old('client_id', $ti->client_id ?? '') == $client->id)>{{ $client->name }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Document Date" />
                    <x-text-input type="date" name="document_date" required class="mt-1 block w-full" value="{{ old('document_date', optional($ti->document_date ?? null)->format('Y-m-d') ?? now()->format('Y-m-d')) }}" />
                </div>
                <div>
                    <x-input-label value="Tax % (GST/VAT)" />
                    <x-text-input type="number" step="0.01" min="0" max="100" name="tax_percent" x-model.number="taxPercent" class="mt-1 block w-full" />
                </div>
            </div>
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Reference Details (optional)</h3>
            <div class="space-y-3">
                <x-text-input name="delivery_note" placeholder="Delivery Note" class="w-full text-sm" value="{{ old('delivery_note', $ti->delivery_note ?? '') }}" />
                <x-text-input name="payment_terms" placeholder="Mode / Terms of Payment" class="w-full text-sm" value="{{ old('payment_terms', $ti->payment_terms ?? '') }}" />
                <x-text-input name="supplier_ref" placeholder="Supplier's Ref." class="w-full text-sm" value="{{ old('supplier_ref', $ti->supplier_ref ?? '') }}" />
                <x-text-input name="other_reference" placeholder="Other Reference(s)" class="w-full text-sm" value="{{ old('other_reference', $ti->other_reference ?? '') }}" />
                <x-text-input name="buyer_order_no" placeholder="Buyer's Order No." class="w-full text-sm" value="{{ old('buyer_order_no', $ti->buyer_order_no ?? '') }}" />
                <x-text-input type="date" name="buyer_order_date" class="w-full text-sm" value="{{ old('buyer_order_date', optional($ti->buyer_order_date ?? null)->format('Y-m-d')) }}" />
                <x-text-input name="dispatch_doc_no" placeholder="Despatch Document No." class="w-full text-sm" value="{{ old('dispatch_doc_no', $ti->dispatch_doc_no ?? '') }}" />
                <x-text-input name="dispatch_through" placeholder="Despatched Through" class="w-full text-sm" value="{{ old('dispatch_through', $ti->dispatch_through ?? '') }}" />
                <x-text-input name="destination" placeholder="Destination" class="w-full text-sm" value="{{ old('destination', $ti->destination ?? '') }}" />
                <x-textarea-input name="terms_of_delivery" rows="2" placeholder="Terms of Delivery" class="w-full text-sm">{{ old('terms_of_delivery', $ti->terms_of_delivery ?? '') }}</x-textarea-input>
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
                        <th class="px-4 py-3 w-20">Unit</th>
                        <th class="px-4 py-3 w-24">Qty</th>
                        <th class="px-4 py-3 w-32">Rate</th>
                        <th class="px-4 py-3 w-32 text-right">Total</th>
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
                                <input type="text" :name="'items['+index+'][unit]'" x-model="item.unit" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" step="0.01" min="0.01" :name="'items['+index+'][quantity]'" x-model.number="item.quantity" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                            </td>
                            <td class="px-4 py-2">
                                <input type="number" step="0.01" min="0" :name="'items['+index+'][rate]'" x-model.number="item.rate" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                            </td>
                            <td class="px-4 py-2 text-right text-sm font-medium text-gray-700 dark:text-gray-300" x-text="money(lineTotal(item))"></td>
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
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Notes / Declaration</h3>
            <x-textarea-input name="notes" rows="4" class="w-full text-sm" placeholder="e.g. We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.">{{ old('notes', $ti->notes ?? '') }}</x-textarea-input>
        </x-card>
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Totals</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Taxable Value</dt><dd x-text="money(subtotal)" class="font-medium text-gray-800 dark:text-gray-200"></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Tax <span x-text="'('+taxPercent+'%)'"></span></dt><dd x-text="money(taxAmount)" class="font-medium text-gray-800 dark:text-gray-200"></dd></div>
                <div class="flex justify-between border-t border-gray-100 pt-2 text-base dark:border-gray-800"><dt class="font-semibold text-gray-800 dark:text-gray-100">Grand Total</dt><dd x-text="money(grandTotal)" class="font-bold text-indigo-600"></dd></div>
            </dl>
        </x-card>
    </div>
</div>
