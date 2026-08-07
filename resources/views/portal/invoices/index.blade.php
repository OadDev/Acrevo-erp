<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Invoices &amp; Payments" />
    </x-slot>

    <x-card :padded="false">
        @if ($invoices->isEmpty())
            <div class="p-6"><x-empty-state icon="receipt" title="No invoices yet" /></div>
        @else
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-5 py-3">Invoice</th>
                        <th class="px-5 py-3">Amount</th>
                        <th class="px-5 py-3">Paid</th>
                        <th class="px-5 py-3">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($invoices as $invoice)
                        <tr>
                            <td class="px-5 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $invoice->invoice_no }}</td>
                            <td class="px-5 py-3">₹{{ number_format($invoice->total_amount, 2) }}</td>
                            <td class="px-5 py-3">₹{{ number_format($invoice->payments->sum('amount'), 2) }}</td>
                            <td class="px-5 py-3"><x-badge :status="$invoice->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-app-layout>
