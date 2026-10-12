<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$deliveryChallan->challan_no" :subtitle="$deliveryChallan->company?->name.' — '.($deliveryChallan->client?->name ?? 'Removed client')">
            <x-slot name="actions">
                <x-badge :status="$deliveryChallan->status" class="text-sm" />
                <a href="{{ route('delivery-challans.pdf', $deliveryChallan) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    <x-icon name="download" class="h-4 w-4" /> Download PDF
                </a>
                @can('delivery_challans.edit')
                    <x-link-button :href="route('delivery-challans.edit', $deliveryChallan)" variant="secondary">Edit</x-link-button>
                @endcan
                @can('delivery_challans.delete')
                    <form method="POST" action="{{ route('delivery-challans.destroy', $deliveryChallan) }}" onsubmit="return confirm('Remove this Delivery Challan? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Remove</button>
                    </form>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Party</h3>
            <dl class="space-y-1 text-sm">
                <div class="font-medium text-gray-800 dark:text-gray-200">{{ $deliveryChallan->client?->name }}</div>
                <div class="text-gray-500">{{ $deliveryChallan->client?->address }}</div>
                <div class="text-gray-500">{{ $deliveryChallan->client?->phone }} · {{ $deliveryChallan->client?->email }}</div>
            </dl>
        </x-card>
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Shipping To</h3>
            <dl class="space-y-1 text-sm">
                <div class="font-medium text-gray-800 dark:text-gray-200">{{ $deliveryChallan->shipping_name ?: $deliveryChallan->client?->name }}</div>
                <div class="text-gray-500">{{ $deliveryChallan->shipping_address ?: $deliveryChallan->client?->address }}</div>
                <div class="text-gray-500">Date: {{ $deliveryChallan->challan_date->format('d M Y') }} @if($deliveryChallan->delivery_time) · {{ $deliveryChallan->delivery_time }} @endif</div>
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
                        <th class="px-4 py-3 text-right">Quantity</th>
                        <th class="px-4 py-3">Unit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($deliveryChallan->items as $item)
                        <tr>
                            <td class="px-4 py-3 text-gray-500">{{ $loop->iteration }}</td>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $item->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $item->hsn_sac_code ?: '—' }}</td>
                            <td class="px-4 py-3 text-right text-gray-500">{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $item->unit }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-2 text-sm font-semibold text-gray-500">Received By</h3>
            <dl class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                <div>{{ $deliveryChallan->received_by_name ?: '—' }}</div>
                <div class="text-gray-400">{{ $deliveryChallan->received_by_comment }}</div>
                <div class="text-gray-400">{{ optional($deliveryChallan->received_by_date)->format('d M Y') }}</div>
            </dl>
        </x-card>
        <x-card>
            <h3 class="mb-2 text-sm font-semibold text-gray-500">Delivered By</h3>
            <dl class="space-y-1 text-sm text-gray-600 dark:text-gray-300">
                <div>{{ $deliveryChallan->delivered_by_name ?: '—' }}</div>
                <div class="text-gray-400">{{ $deliveryChallan->delivered_by_comment }}</div>
                <div class="text-gray-400">{{ optional($deliveryChallan->delivered_by_date)->format('d M Y') }}</div>
            </dl>
        </x-card>
    </div>
</x-app-layout>
