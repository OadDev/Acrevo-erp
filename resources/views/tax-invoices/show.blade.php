<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$taxInvoice->tax_invoice_no" :subtitle="$taxInvoice->company?->name.' — '.($taxInvoice->client?->name ?? 'Removed client')">
            <x-slot name="actions">
                <x-badge :status="$taxInvoice->status" class="text-sm" />
                <a href="{{ route('tax-invoices.pdf', $taxInvoice) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    <x-icon name="download" class="h-4 w-4" /> Download PDF
                </a>
                @can('tax_invoices.edit')
                    <x-link-button :href="route('tax-invoices.edit', $taxInvoice)" variant="secondary">Edit</x-link-button>
                @endcan
                @can('tax_invoices.delete')
                    <form method="POST" action="{{ route('tax-invoices.destroy', $taxInvoice) }}" onsubmit="return confirm('Remove this Tax Invoice? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Remove</button>
                    </form>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    @if ($taxInvoice->proformaInvoice)
        <div class="mb-4 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400">
            Converted from Proforma Invoice <a href="{{ route('proforma-invoices.show', $taxInvoice->proformaInvoice) }}" class="font-semibold hover:underline">{{ $taxInvoice->proformaInvoice->proforma_no }}</a>.
        </div>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Buyer</h3>
            <dl class="space-y-1 text-sm">
                <div class="font-medium text-gray-800 dark:text-gray-200">{{ $taxInvoice->client?->name }}</div>
                <div class="text-gray-500">{{ $taxInvoice->client?->address }}</div>
                <div class="text-gray-500">{{ $taxInvoice->client?->phone }} · {{ $taxInvoice->client?->email }}</div>
                <div class="text-gray-500">{{ $taxInvoice->company?->taxIdLabel() }}: {{ $taxInvoice->client?->gstin ?: '—' }}</div>
            </dl>
        </x-card>
        <x-card>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Date</dt><dd>{{ $taxInvoice->document_date->format('d M Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Created By</dt><dd>{{ $taxInvoice->createdBy?->name ?? '—' }}</dd></div>
            </dl>
        </x-card>
    </div>

    <x-card :padded="false" class="mt-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">S.No</th>
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">HSN/SAC</th>
                        <th class="px-4 py-3">Unit</th>
                        <th class="px-4 py-3 text-right">Qty</th>
                        <th class="px-4 py-3 text-right">Rate</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($taxInvoice->items as $item)
                        <tr>
                            <td class="px-4 py-3 text-gray-500">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $item->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $item->hsn_sac_code ?: '—' }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $item->unit }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">{{ number_format($item->rate, 2) }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">{{ number_format($item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="border-t border-gray-100 p-4 dark:border-gray-800">
            <dl class="ml-auto w-64 space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Taxable Value</dt><dd>{{ $taxInvoice->company?->currencySymbol() }} {{ number_format($taxInvoice->subtotal, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">{{ $taxInvoice->company?->taxLabel() }} ({{ $taxInvoice->tax_percent }}%)</dt><dd>{{ $taxInvoice->company?->currencySymbol() }} {{ number_format($taxInvoice->tax_amount, 2) }}</dd></div>
                <div class="flex justify-between border-t border-gray-100 pt-2 text-base font-bold text-indigo-600 dark:border-gray-800"><dt>Grand Total</dt><dd>{{ $taxInvoice->company?->currencySymbol() }} {{ number_format($taxInvoice->total_amount, 2) }}</dd></div>
            </dl>
        </div>
    </x-card>
</x-app-layout>
