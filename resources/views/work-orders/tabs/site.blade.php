<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <x-card class="lg:col-span-2">
        <h3 class="mb-4 text-sm font-semibold text-gray-500">Site Details</h3>

        @if ($workOrder->site)
            <dl class="mb-6 grid grid-cols-2 gap-4 text-sm">
                <div class="col-span-2"><dt class="text-gray-400">Site ID</dt><dd class="text-gray-800 dark:text-gray-200"><a href="{{ route('sites.show', $workOrder->site) }}" class="text-indigo-600 hover:underline">{{ $workOrder->site->site_no }}</a></dd></div>
                <div><dt class="text-gray-400">Client</dt><dd class="text-gray-800 dark:text-gray-200">{{ $workOrder->client?->name ?? 'Unknown client' }}</dd></div>
                <div><dt class="text-gray-400">Quotation</dt><dd class="text-gray-800 dark:text-gray-200">{{ $workOrder->quotation->quotation_no ?? '—' }}</dd></div>
            </dl>

            @can('work_orders.edit')
                <form method="POST" action="{{ route('work-orders.site.update', $workOrder) }}" class="grid grid-cols-1 gap-5 border-t border-gray-100 pt-6 dark:border-gray-800 sm:grid-cols-2">
                    @csrf
                    @method('PATCH')
                    <div class="sm:col-span-2">
                        <x-input-label for="address" value="Address" />
                        <x-text-input id="address" name="address" class="mt-1 block w-full" value="{{ old('address', $workOrder->site->address) }}" />
                    </div>
                    <div>
                        <x-input-label for="city" value="City" />
                        <x-text-input id="city" name="city" class="mt-1 block w-full" value="{{ old('city', $workOrder->site->city) }}" />
                    </div>
                    <div>
                        <x-input-label for="state" value="State" />
                        <x-text-input id="state" name="state" class="mt-1 block w-full" value="{{ old('state', $workOrder->site->state) }}" />
                    </div>
                    <div>
                        <x-input-label for="pincode" value="Pincode" />
                        <x-text-input id="pincode" name="pincode" class="mt-1 block w-full" value="{{ old('pincode', $workOrder->site->pincode) }}" />
                    </div>
                    <div>
                        <x-input-label for="site_contact_name" value="Site Contact Name" />
                        <x-text-input id="site_contact_name" name="site_contact_name" class="mt-1 block w-full" value="{{ old('site_contact_name', $workOrder->site->site_contact_name) }}" />
                    </div>
                    <div>
                        <x-input-label for="site_contact_phone" value="Site Contact Phone" />
                        <x-text-input id="site_contact_phone" name="site_contact_phone" class="mt-1 block w-full" value="{{ old('site_contact_phone', $workOrder->site->site_contact_phone) }}" />
                    </div>
                    <div class="sm:col-span-2">
                        <x-primary-button type="submit">Save Site Details</x-primary-button>
                    </div>
                </form>
            @else
                <dl class="space-y-2 border-t border-gray-100 pt-6 text-sm dark:border-gray-800">
                    <div><dt class="text-gray-400">Address</dt><dd class="text-gray-800 dark:text-gray-200">{{ collect([$workOrder->site->address, $workOrder->site->city, $workOrder->site->state, $workOrder->site->pincode])->filter()->join(', ') ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400">Site Contact</dt><dd class="text-gray-800 dark:text-gray-200">{{ $workOrder->site->site_contact_name ?? '—' }} @if ($workOrder->site->site_contact_phone) · {{ $workOrder->site->site_contact_phone }} @endif</dd></div>
                </dl>
            @endcan
        @else
            <p class="text-sm text-gray-400">No site linked to this work order.</p>
        @endif
    </x-card>

    <x-card>
        <h3 class="mb-4 text-sm font-semibold text-gray-500">Other Work Orders at this Site</h3>
        @forelse (($workOrder->site->workOrders ?? collect())->where('id', '!=', $workOrder->id) as $sibling)
            <a href="{{ route('work-orders.show', $sibling) }}" class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                <span class="text-gray-700 dark:text-gray-300">{{ $sibling->work_order_no }} — {{ $sibling->title }}</span>
                <x-badge :status="$sibling->status" />
            </a>
        @empty
            <p class="text-sm text-gray-400">None yet.</p>
        @endforelse
    </x-card>
</div>
