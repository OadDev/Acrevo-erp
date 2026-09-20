<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Quotations" subtitle="Client-approved quotations become Work Orders." />
    </x-slot>

    <x-card :padded="false">
        @if ($quotations->isEmpty())
            <div class="p-6">
                <x-empty-state icon="file-text" title="No quotations yet" description="Create a quotation from any enquiry to get started." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Quotation</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Amount</th>
                            <th class="px-5 py-3">Status</th>
                            @if (auth()->user()->hasRole('Admin'))
                                <th class="px-5 py-3"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($quotations as $quotation)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="cursor-pointer px-5 py-3" onclick="window.location='{{ route('quotations.show', $quotation) }}'">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $quotation->quotation_no }}</span>
                                    <p class="text-xs text-gray-400">v{{ $quotation->version }}</p>
                                </td>
                                <td class="cursor-pointer px-5 py-3 text-sm text-gray-600 dark:text-gray-300" onclick="window.location='{{ route('quotations.show', $quotation) }}'">{{ $quotation->client?->name ?? 'Unknown client' }}</td>
                                <td class="cursor-pointer px-5 py-3 text-sm font-medium text-gray-800 dark:text-gray-200" onclick="window.location='{{ route('quotations.show', $quotation) }}'">₹{{ number_format($quotation->total_amount, 2) }}</td>
                                <td class="cursor-pointer px-5 py-3" onclick="window.location='{{ route('quotations.show', $quotation) }}'"><x-badge :status="$quotation->status" /></td>
                                @if (auth()->user()->hasRole('Admin'))
                                    <td class="px-5 py-3 text-right">
                                        @if ($quotation->workOrders->isEmpty())
                                            <form method="POST" action="{{ route('quotations.destroy', $quotation) }}" onsubmit="return confirm('Permanently remove this quotation? This cannot be undone.')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="text-sm text-rose-600 hover:underline">Remove</button>
                                            </form>
                                        @else
                                            <span class="text-sm text-gray-300" title="Remove all work orders generated from this quotation first">Remove</span>
                                        @endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $quotations->links() }}</div>
</x-app-layout>
