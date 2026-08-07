<x-card :padded="false">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <th class="px-4 py-3 w-32">Type</th>
                    <th class="px-4 py-3">Item</th>
                    <th class="px-4 py-3 w-24">Unit</th>
                    <th class="px-4 py-3 w-24">Qty</th>
                    <th class="px-4 py-3 w-32">Unit Price</th>
                    <th class="px-4 py-3 w-28">Discount</th>
                    <th class="px-4 py-3 w-32 text-right">Total</th>
                    <th class="px-2 py-3 w-8"></th>
                </tr>
            </thead>
            <tbody>
                <template x-for="(item, index) in items" :key="index">
                    <tr class="border-b border-gray-100 dark:border-gray-800">
                        <td class="px-4 py-2">
                            <select :name="'items['+index+'][item_type]'" x-model="item.item_type" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                                <option value="service">Service</option>
                                <option value="product">Product</option>
                            </select>
                        </td>
                        <td class="px-4 py-2">
                            <input type="text" :name="'items['+index+'][name]'" x-model="item.name" placeholder="Item name" required class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                            <input type="text" :name="'items['+index+'][description]'" x-model="item.description" placeholder="Description (optional)" class="mt-1 w-full rounded-md border-gray-200 text-xs dark:border-gray-800 dark:bg-gray-900">
                        </td>
                        <td class="px-4 py-2">
                            <input type="text" :name="'items['+index+'][unit]'" x-model="item.unit" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                        </td>
                        <td class="px-4 py-2">
                            <input type="number" step="0.01" min="0.01" :name="'items['+index+'][quantity]'" x-model.number="item.quantity" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                        </td>
                        <td class="px-4 py-2">
                            <input type="number" step="0.01" min="0" :name="'items['+index+'][unit_price]'" x-model.number="item.unit_price" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                        </td>
                        <td class="px-4 py-2">
                            <input type="number" step="0.01" min="0" :name="'items['+index+'][discount]'" x-model.number="item.discount" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
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
        <h3 class="mb-4 text-sm font-semibold text-gray-500">Terms &amp; Validity</h3>
        <div class="space-y-4">
            <div>
                <x-input-label value="Terms & Conditions" />
                <x-textarea-input name="terms" rows="4" class="mt-1 block w-full">{{ old('terms', $quotation->terms ?? '') }}</x-textarea-input>
            </div>
            <div>
                <x-input-label value="Valid Until" />
                <x-text-input type="date" name="valid_until" class="mt-1 block w-full" value="{{ old('valid_until', optional($quotation->valid_until ?? null)->format('Y-m-d')) }}" />
            </div>
        </div>
    </x-card>

    <x-card>
        <h3 class="mb-4 text-sm font-semibold text-gray-500">Pricing Summary</h3>
        <div class="space-y-3">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <x-input-label value="Discount Type" />
                    <select name="discount_type" x-model="discountType" class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="flat">Flat (₹)</option>
                        <option value="percent">Percent (%)</option>
                    </select>
                </div>
                <div>
                    <x-input-label value="Discount Value" />
                    <input type="number" step="0.01" min="0" name="discount_value" x-model.number="discountValue" class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                </div>
            </div>
            <div>
                <x-input-label value="Tax %" />
                <input type="number" step="0.01" min="0" max="100" name="tax_percent" x-model.number="taxPercent" class="mt-1 w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
            </div>
            <dl class="mt-4 space-y-2 border-t border-gray-100 pt-4 text-sm dark:border-gray-800">
                <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd x-text="money(subtotal)" class="font-medium text-gray-800 dark:text-gray-200"></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Discount</dt><dd x-text="'- ' + money(discountAmount)" class="font-medium text-rose-500"></dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Tax</dt><dd x-text="money(taxAmount)" class="font-medium text-gray-800 dark:text-gray-200"></dd></div>
                <div class="flex justify-between border-t border-gray-100 pt-2 text-base dark:border-gray-800"><dt class="font-semibold text-gray-800 dark:text-gray-100">Grand Total</dt><dd x-text="money(grandTotal)" class="font-bold text-indigo-600"></dd></div>
            </dl>
        </div>
    </x-card>
</div>
