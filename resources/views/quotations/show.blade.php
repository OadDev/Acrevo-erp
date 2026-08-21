<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$quotation->quotation_no" :subtitle="'v'.$quotation->version.' — '.$quotation->client->name">
            <x-slot name="actions">
                <x-badge :status="$quotation->status" class="text-sm" />
                <x-link-button :href="route('quotations.pdf', $quotation)" variant="secondary"><x-icon name="download" class="h-4 w-4" /> PDF</x-link-button>

                @if ($quotation->status === 'draft')
                    @can('quotations.edit')
                        <x-link-button :href="route('quotations.edit', $quotation)" variant="secondary">Edit</x-link-button>
                    @endcan
                    @can('quotations.send')
                        <form method="POST" action="{{ route('quotations.send', $quotation) }}">
                            @csrf
                            <x-primary-button>Send to Client</x-primary-button>
                        </form>
                    @endcan
                @endif

                @if ($quotation->status === 'sent')
                    @can('quotations.approve')
                        <form method="POST" action="{{ route('quotations.approve', $quotation) }}">
                            @csrf
                            <x-primary-button>Mark Approved</x-primary-button>
                        </form>
                        <form method="POST" action="{{ route('quotations.reject', $quotation) }}" onsubmit="return promptReject(event, this)">
                            @csrf
                            <input type="hidden" name="rejected_reason" value="">
                            <button type="submit" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Reject</button>
                        </form>
                    @endcan
                @endif

                @if (in_array($quotation->status, ['sent', 'rejected', 'expired']))
                    @can('quotations.edit')
                        <form method="POST" action="{{ route('quotations.revise', $quotation) }}">
                            @csrf
                            <button class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Create Revision</button>
                        </form>
                    @endcan
                @endif

                @if ($quotation->status === 'approved' && $quotation->workOrders->isEmpty())
                    @can('work_orders.create')
                        <x-link-button :href="route('work-orders.create', ['quotation_id' => $quotation->id])">Generate Work Order</x-link-button>
                    @endcan
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card :padded="false" class="mb-6">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Item</th>
                        <th class="px-4 py-3">Unit</th>
                        <th class="px-4 py-3">Qty</th>
                        <th class="px-4 py-3">Unit Price</th>
                        <th class="px-4 py-3 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($quotation->items as $item)
                        <tr>
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800 dark:text-gray-200">{{ $item->name }}</p>
                                <p class="text-xs text-gray-400">{{ $item->description }}</p>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $item->unit }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $item->quantity }}</td>
                            <td class="px-4 py-3 text-sm text-gray-600 dark:text-gray-300">₹{{ number_format($item->unit_price, 2) }}</td>
                            <td class="px-4 py-3 text-right text-sm font-medium text-gray-800 dark:text-gray-200">₹{{ number_format($item->total, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="flex justify-end border-t border-gray-100 p-4 dark:border-gray-800">
            <dl class="w-64 space-y-1 text-sm">
                <div class="flex justify-between"><dt class="text-gray-500">Subtotal</dt><dd>₹{{ number_format($quotation->subtotal, 2) }}</dd></div>
                <div class="flex justify-between"><dt class="text-gray-500">Tax</dt><dd>₹{{ number_format($quotation->tax_amount, 2) }}</dd></div>
                <div class="flex justify-between text-base font-semibold"><dt>Total</dt><dd class="text-indigo-600">₹{{ number_format($quotation->total_amount, 2) }}</dd></div>
            </dl>
        </div>
    </x-card>

    @if ($quotation->rejected_reason)
        <div class="mb-6 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            Rejected: {{ $quotation->rejected_reason }}
        </div>
    @endif

    @if ($quotation->terms)
        <x-card class="mb-6">
            <h3 class="mb-2 text-sm font-semibold text-gray-500">Terms &amp; Conditions</h3>
            <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $quotation->terms }}</p>
        </x-card>
    @endif

    <x-discussion-card :conversation="$discussion" />

    @push('scripts')
    <script>
        function promptReject(event, form) {
            const reason = prompt('Reason for rejection:');
            if (!reason) { event.preventDefault(); return false; }
            form.querySelector('input[name=rejected_reason]').value = reason;
            return true;
        }
    </script>
    @endpush
</x-app-layout>
