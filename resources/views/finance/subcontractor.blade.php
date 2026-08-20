<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Finance" subtitle="Payments made to you by the company." />
    </x-slot>

    <x-card class="mb-6 max-w-xs">
        <p class="text-sm text-gray-500">Total Received</p>
        <p class="mt-2 text-2xl font-semibold text-emerald-600">₹{{ number_format($totalReceived, 2) }}</p>
    </x-card>

    <x-card :padded="false">
        @if ($payments->isEmpty())
            <div class="p-6"><x-empty-state icon="banknote" title="No payments recorded yet" description="Payments made to you by Finance/Admin will appear here." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Work Order</th>
                            <th class="px-5 py-3">Category</th>
                            <th class="px-5 py-3">Mode</th>
                            <th class="px-5 py-3 text-right">Amount</th>
                            <th class="px-5 py-3">Remark</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($payments as $payment)
                            <tr>
                                <td class="px-5 py-3 text-gray-500">{{ $payment->payment_date->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ $payment->workOrder?->work_order_no ?? '—' }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ $payment->category ?? '—' }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ Str::title(str_replace('_',' ',$payment->mode)) }}</td>
                                <td class="px-5 py-3 text-right font-medium text-emerald-600">₹{{ number_format($payment->amount, 2) }}</td>
                                <td class="px-5 py-3 text-gray-500">{{ $payment->remark ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $payments->links() }}</div>
</x-app-layout>
