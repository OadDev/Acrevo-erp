<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$site->site_no" :subtitle="$site->client->name">
            <x-slot name="actions">
                <x-badge :status="$site->status" class="text-sm" />
                @can('work_orders.edit')
                    @if ($site->status === 'active')
                        <form method="POST" action="{{ route('sites.complete', $site) }}" onsubmit="return confirm('Mark this site as completed and hand it over to the client? This should be the final step once all work here is done.')">
                            @csrf
                            <x-primary-button type="submit">Completed Site Work — Handover</x-primary-button>
                        </form>
                    @endif
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Site Details</h3>

            @can('work_orders.edit')
                <form method="POST" action="{{ route('sites.update', $site) }}" class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    @csrf
                    @method('PATCH')
                    <div class="sm:col-span-2">
                        <x-input-label for="address" value="Address" />
                        <x-text-input id="address" name="address" class="mt-1 block w-full" value="{{ old('address', $site->address) }}" />
                    </div>
                    <div>
                        <x-input-label for="city" value="City" />
                        <x-text-input id="city" name="city" class="mt-1 block w-full" value="{{ old('city', $site->city) }}" />
                    </div>
                    <div>
                        <x-input-label for="state" value="State" />
                        <x-text-input id="state" name="state" class="mt-1 block w-full" value="{{ old('state', $site->state) }}" />
                    </div>
                    <div>
                        <x-input-label for="pincode" value="Pincode" />
                        <x-text-input id="pincode" name="pincode" class="mt-1 block w-full" value="{{ old('pincode', $site->pincode) }}" />
                    </div>
                    <div>
                        <x-input-label for="site_contact_name" value="Site Contact Name" />
                        <x-text-input id="site_contact_name" name="site_contact_name" class="mt-1 block w-full" value="{{ old('site_contact_name', $site->site_contact_name) }}" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-input-label for="site_contact_phone" value="Site Contact Phone" />
                        <x-text-input id="site_contact_phone" name="site_contact_phone" class="mt-1 block w-full" value="{{ old('site_contact_phone', $site->site_contact_phone) }}" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-primary-button type="submit">Save Site Details</x-primary-button>
                    </div>
                </form>
            @else
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-400">Address</dt><dd class="text-gray-800 dark:text-gray-200">{{ collect([$site->address, $site->city, $site->state, $site->pincode])->filter()->join(', ') ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400">Site Contact</dt><dd class="text-gray-800 dark:text-gray-200">{{ $site->site_contact_name ?? '—' }} @if ($site->site_contact_phone) · {{ $site->site_contact_phone }} @endif</dd></div>
                </dl>
            @endcan
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Client</h3>
            <dl class="space-y-2 text-sm">
                <div><dt class="text-gray-400">Client</dt><dd class="text-gray-800 dark:text-gray-200"><a href="{{ route('clients.show', $site->client) }}" class="text-indigo-600 hover:underline">{{ $site->client->name }}</a></dd></div>
                <div><dt class="text-gray-400">Quotation</dt><dd class="text-gray-800 dark:text-gray-200">
                    @if ($site->quotation)
                        <a href="{{ route('quotations.show', $site->quotation) }}" class="text-indigo-600 hover:underline">{{ $site->quotation->quotation_no }}</a>
                    @else
                        — (added manually)
                    @endif
                </dd></div>
            </dl>
        </x-card>
    </div>

    @include('sites.partials.documents', ['site' => $site])

    @if ($site->status === 'completed')
        <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
            This site was marked completed and handed over to the client{{ $site->completed_at ? ' on '.$site->completed_at->format('d M Y') : '' }}.
        </div>
    @endif

    <x-card :padded="false" class="mt-6">
        <div class="flex items-center justify-between border-b border-gray-100 px-5 py-3 dark:border-gray-800">
            <h3 class="text-sm font-semibold text-gray-500">Work Orders at this Site</h3>
            @can('work_orders.create')
                @if ($site->status === 'active' && $site->quotation && $site->quotation->status === 'approved')
                    <x-link-button :href="route('work-orders.create', ['quotation_id' => $site->quotation_id])" class="text-xs">+ Create Work Order</x-link-button>
                @endif
            @endcan
        </div>

        @if ($workOrders->isEmpty())
            <div class="p-6">
                <x-empty-state icon="clipboard" title="No work orders yet" description="Work orders created for this site will appear here." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Work Order</th>
                            <th class="px-5 py-3">Priority</th>
                            <th class="px-5 py-3">Deadline</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($workOrders as $workOrder)
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('work-orders.show', $workOrder) }}'">
                                <td class="px-5 py-3">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $workOrder->work_order_no }}</span>
                                    <p class="text-xs text-gray-400">{{ $workOrder->title }}</p>
                                </td>
                                <td class="px-5 py-3"><x-badge :status="$workOrder->priority" /></td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ optional($workOrder->deadline)->format('d M Y') ?? '—' }}</td>
                                <td class="px-5 py-3"><x-badge :status="$workOrder->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $workOrders->links() }}</div>
</x-app-layout>
