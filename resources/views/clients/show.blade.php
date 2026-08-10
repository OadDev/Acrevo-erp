<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$client->name" :subtitle="$client->client_code">
            <x-slot name="actions">
                <x-link-button :href="route('clients.edit', $client)" variant="secondary">Edit</x-link-button>
                @can('enquiries.create')
                    <x-link-button :href="route('enquiries.create', ['client_id' => $client->id])">New Enquiry</x-link-button>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-1">
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Contact Details</h3>
            <dl class="space-y-3 text-sm">
                <div><dt class="text-gray-400">Phone</dt><dd class="text-gray-800 dark:text-gray-200">{{ $client->phone ?: '—' }}</dd></div>
                <div><dt class="text-gray-400">Email</dt><dd class="text-gray-800 dark:text-gray-200">{{ $client->email ?: '—' }}</dd></div>
                <div><dt class="text-gray-400">Address</dt><dd class="text-gray-800 dark:text-gray-200">{{ $client->address ?: '—' }}, {{ $client->city }} {{ $client->state }}</dd></div>
                <div><dt class="text-gray-400">GSTIN</dt><dd class="text-gray-800 dark:text-gray-200">{{ $client->gstin ?: '—' }}</dd></div>
                <div><dt class="text-gray-400">Assigned Sales</dt><dd class="text-gray-800 dark:text-gray-200">{{ $client->assignedSales?->name ?? '—' }}</dd></div>
            </dl>

            <div class="mt-6 border-t border-gray-100 pt-4 dark:border-gray-800">
                <h3 class="mb-3 text-sm font-semibold text-gray-500">Client Portal</h3>
                @if ($client->clientLogin)
                    <p class="text-sm text-emerald-600 dark:text-emerald-400">Portal access active — {{ $client->clientLogin->user->email }}</p>
                @else
                    @can('enquiries.create')
                        <form method="POST" action="{{ route('clients.portal-access', $client) }}" onsubmit="return confirm('Create portal login for {{ $client->email }}?')">
                            @csrf
                            <x-primary-button type="submit">Generate Portal Access</x-primary-button>
                        </form>
                        @unless ($client->email)
                            <p class="mt-2 text-xs text-gray-400">Add an email address first.</p>
                        @endunless
                    @endcan
                @endif
            </div>
        </x-card>

        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Enquiries</h3>
                @forelse ($client->enquiries as $enquiry)
                    <a href="{{ route('enquiries.show', $enquiry) }}" class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <span class="text-gray-700 dark:text-gray-300">{{ $enquiry->enquiry_no }} — {{ $enquiry->service_type }}</span>
                        <x-badge :status="$enquiry->status" />
                    </a>
                @empty
                    <p class="text-sm text-gray-400">No enquiries yet.</p>
                @endforelse
            </x-card>

            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Work Orders</h3>
                @forelse ($client->workOrders as $workOrder)
                    <a href="{{ route('work-orders.show', $workOrder) }}" class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <span class="text-gray-700 dark:text-gray-300">{{ $workOrder->work_order_no }} — {{ $workOrder->title }}</span>
                        <x-badge :status="$workOrder->status" />
                    </a>
                @empty
                    <p class="text-sm text-gray-400">No work orders yet.</p>
                @endforelse
            </x-card>
        </div>
    </div>
</x-app-layout>
