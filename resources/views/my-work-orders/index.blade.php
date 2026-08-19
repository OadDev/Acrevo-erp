<x-app-layout>
    <x-slot name="header">
        <x-page-header title="My Work Orders" subtitle="Work orders assigned to you." />
    </x-slot>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($workOrders as $workOrder)
            <a href="{{ route('work-orders.show', $workOrder) }}">
                <x-card class="h-full transition hover:border-indigo-300">
                    <div class="flex items-center justify-between">
                        <p class="font-medium text-gray-900 dark:text-white">{{ $workOrder->title }}</p>
                        <x-badge :status="$workOrder->status" />
                    </div>
                    <p class="mt-1 text-xs text-gray-400">{{ $workOrder->work_order_no }} — {{ $workOrder->client->name }}</p>
                    <p class="mt-3 text-sm text-gray-500">Deadline: {{ optional($workOrder->deadline)->format('d M Y') ?? '—' }}</p>
                </x-card>
            </a>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <x-empty-state icon="hard-hat" title="No work orders assigned yet" />
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $workOrders->links() }}</div>

    @if ($isSubContractor)
        <x-card :padded="false" class="mt-8">
            <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">My Payments</h3></div>
            @if ($myPayments->isEmpty())
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
                            @foreach ($myPayments as $payment)
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
    @endif
</x-app-layout>
