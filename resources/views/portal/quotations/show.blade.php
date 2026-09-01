<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$quotation->quotation_no" subtitle="Please review the quotation below.">
            <x-slot name="actions">
                <x-badge :status="$quotation->status" class="text-sm" />
                <x-link-button :href="route('portal.quotations.pdf', $quotation)" variant="secondary"><x-icon name="download" class="h-4 w-4" /> Download PDF</x-link-button>
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

    @if ($quotation->terms)
        <x-card class="mb-6">
            <h3 class="mb-2 text-sm font-semibold text-gray-500">Terms &amp; Conditions</h3>
            <p class="whitespace-pre-line text-sm text-gray-600 dark:text-gray-300">{{ $quotation->terms }}</p>
        </x-card>
    @endif

    @if ($quotation->media->isNotEmpty())
        <x-card class="mb-6">
            <h3 class="mb-3 text-sm font-semibold text-gray-500">Attachments</h3>
            <div class="flex flex-wrap gap-3">
                @foreach ($quotation->media as $attachment)
                    <a href="{{ $attachment->getUrl() }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-indigo-600 hover:underline dark:border-gray-700">
                        <x-icon name="paperclip" class="h-4 w-4" /> {{ $attachment->file_name }}
                    </a>
                @endforeach
            </div>
        </x-card>
    @endif

    @if ($quotation->status === 'sent')
        <x-card>
            <h3 class="mb-3 text-sm font-semibold text-gray-500">Your Decision</h3>
            <div class="flex flex-wrap gap-2">
                <form method="POST" action="{{ route('portal.quotations.approve', $quotation) }}">
                    @csrf
                    <x-primary-button>Accept Quotation</x-primary-button>
                </form>
                <button type="button" onclick="document.getElementById('reject-form').classList.remove('hidden')" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Reject</button>
                <button type="button" onclick="document.getElementById('requote-form').classList.remove('hidden')" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Request Re-Quote</button>
            </div>

            <form id="reject-form" method="POST" action="{{ route('portal.quotations.reject', $quotation) }}" class="mt-4 hidden space-y-2">
                @csrf
                <input type="hidden" name="decision" value="reject">
                <x-input-label value="Reason for rejecting" />
                <x-textarea-input name="reason" rows="3" class="w-full" required></x-textarea-input>
                <x-primary-button type="submit">Confirm Rejection</x-primary-button>
            </form>

            <form id="requote-form" method="POST" action="{{ route('portal.quotations.reject', $quotation) }}" class="mt-4 hidden space-y-2">
                @csrf
                <input type="hidden" name="decision" value="requote">
                <x-input-label value="What would you like changed?" />
                <x-textarea-input name="reason" rows="3" class="w-full" required></x-textarea-input>
                <x-primary-button type="submit">Send Re-Quote Request</x-primary-button>
            </form>
        </x-card>
    @endif

    @if ($quotation->status === 'rejected' && $quotation->rejected_reason)
        <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
            {{ $quotation->rejected_reason }}
        </div>
    @endif
</x-app-layout>
