<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Invoices & Payments" subtitle="Payment requests from our team, and your payment history against them." />
    </x-slot>

    <x-card :padded="false">
        @if ($invoices->isEmpty())
            <div class="p-6"><x-empty-state icon="receipt" title="No invoices yet" /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Invoice</th>
                            <th class="px-5 py-3">Work Order</th>
                            <th class="px-5 py-3">Amount</th>
                            <th class="px-5 py-3">Paid</th>
                            <th class="px-5 py-3">Balance Due</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($invoices as $invoice)
                            <tr class="{{ $invoice->balanceDue() > 0 ? 'bg-amber-50/50 dark:bg-amber-500/5' : '' }}">
                                <td class="px-5 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $invoice->invoice_no }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ $invoice->workOrder?->work_order_no ?? '—' }}</td>
                                <td class="px-5 py-3">₹{{ number_format($invoice->total_amount, 2) }}</td>
                                <td class="px-5 py-3 text-emerald-600">₹{{ number_format($invoice->paidAmount(), 2) }}</td>
                                <td class="px-5 py-3 {{ $invoice->balanceDue() > 0 ? 'font-semibold text-rose-600' : 'text-gray-400' }}">₹{{ number_format($invoice->balanceDue(), 2) }}</td>
                                <td class="px-5 py-3">
                                    @if ($invoice->balanceDue() > 0 && $invoice->status !== 'cancelled')
                                        <x-badge status="payment requested" color="amber" />
                                    @else
                                        <x-badge :status="$invoice->status" />
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $invoices->links() }}</div>
</x-app-layout>
