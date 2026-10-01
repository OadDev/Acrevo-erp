<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Equipment &amp; Assets" subtitle="Machines, tools, and equipment tracked across the company store and every site.">
            <x-slot name="actions">
                @can('assets.restore')
                    <x-link-button :href="route('assets.index', ['removed' => $showRemoved ? null : 1])" variant="secondary">{{ $showRemoved ? 'Back to Active Assets' : 'Removed Assets' }}</x-link-button>
                @endcan
                @can('assets.create')
                    <x-link-button :href="route('assets.create')"><x-icon name="plus" class="h-4 w-4" /> New Asset</x-link-button>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    @unless (request()->hasAny(['q', 'category', 'status', 'current_location', 'work_order_id', 'brand', 'warranty_status', 'purchase_from', 'purchase_to']))
    <div class="mb-4 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-card class="flex items-start justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Total at Company Store</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $companyStoreSummary['total'] }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                <x-icon name="layers" class="h-5 w-5" />
            </div>
        </x-card>
        <x-card class="flex items-start justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Available</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $companyStoreSummary['available'] }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                <x-icon name="check-circle" class="h-5 w-5" />
            </div>
        </x-card>
        <x-card class="flex items-start justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Damaged</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $companyStoreSummary['damaged'] }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                <x-icon name="alert-triangle" class="h-5 w-5" />
            </div>
        </x-card>
        <x-card class="flex items-start justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Missing</p>
                <p class="mt-2 text-2xl font-semibold text-gray-900 dark:text-white">{{ $companyStoreSummary['missing'] }}</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400">
                <x-icon name="search" class="h-5 w-5" />
            </div>
        </x-card>
    </div>

    <x-card :padded="false" class="mb-4">
        <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Company Store — Asset-wise Summary</h3></div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-2">Asset Name</th>
                        <th class="px-4 py-2 text-right">Total</th>
                        <th class="px-4 py-2 text-right">Available</th>
                        <th class="px-4 py-2 text-right">Damaged</th>
                        <th class="px-4 py-2 text-right">Missing</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($companyStoreAssetSummary as $row)
                        <tr>
                            <td class="px-4 py-2 font-medium text-gray-800 dark:text-gray-200">{{ $row->asset?->name ?? 'Removed Asset' }}</td>
                            <td class="px-4 py-2 text-right text-gray-500">{{ $row->total }}</td>
                            <td class="px-4 py-2 text-right text-emerald-600">{{ $row->in_use }}</td>
                            <td class="px-4 py-2 text-right text-amber-600">{{ $row->damaged }}</td>
                            <td class="px-4 py-2 text-right text-rose-600">{{ $row->missing }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No stock currently at the Company Store.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    @if ($pendingMovements->isNotEmpty())
        <x-card :padded="false" class="mb-4">
            <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Waiting for Confirmation</h3></div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-2">Asset</th>
                            <th class="px-4 py-2">From</th>
                            <th class="px-4 py-2 text-right">Qty</th>
                            <th class="px-4 py-2">Sent</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($pendingMovements as $movement)
                            <tr>
                                <td class="px-4 py-2">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $movement->asset->name ?? 'Removed Asset' }}</p>
                                    <p class="text-xs text-gray-400">{{ $movement->asset->asset_code ?? '—' }}</p>
                                </td>
                                <td class="px-4 py-2 text-gray-500">{{ $movement->locationLabel($movement->from_location, $movement->fromWorkOrder) }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ $movement->quantity }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $movement->moved_at->format('d M Y') }}</td>
                                <td class="px-4 py-2"><x-badge status="waiting_for_confirmation" /></td>
                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                    @if ($canConfirmHere)
                                        <form method="POST" action="{{ route('asset-movements.confirm', $movement) }}" class="inline">
                                            @csrf
                                            <button class="text-xs font-medium text-emerald-600 hover:underline">Confirm</button>
                                        </form>
                                        <form method="POST" action="{{ route('asset-movements.cancel', $movement) }}" class="inline" onsubmit="return confirm('Cancel this movement?')">
                                            @csrf
                                            <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Cancel</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
    @endunless

    <x-card class="mb-4">
        <form method="GET" action="{{ route('assets.index') }}" class="space-y-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by Asset ID, name, serial number, brand, model, category, supplier, or work order..." class="w-full rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                <x-select-input name="category" class="text-sm">
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category }}" @selected(request('category') === $category)>{{ $category }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="status" class="text-sm">
                    <option value="">All Statuses</option>
                    @foreach (\App\Models\Asset::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="current_location" class="text-sm">
                    <option value="">All Locations</option>
                    @foreach (\App\Models\Asset::LOCATIONS as $location)
                        <option value="{{ $location }}" @selected(request('current_location') === $location)>{{ ucwords(str_replace('_', ' ', $location)) }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="work_order_id" class="text-sm">
                    <option value="">All Work Orders</option>
                    @foreach ($workOrders as $wo)
                        <option value="{{ $wo->id }}" @selected(request('work_order_id') == $wo->id)>{{ $wo->work_order_no }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="brand" class="text-sm">
                    <option value="">All Brands</option>
                    @foreach ($brands as $brand)
                        <option value="{{ $brand }}" @selected(request('brand') === $brand)>{{ $brand }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="warranty_status" class="text-sm">
                    <option value="">Any Warranty Status</option>
                    <option value="active" @selected(request('warranty_status') === 'active')>Active</option>
                    <option value="expiring_soon" @selected(request('warranty_status') === 'expiring_soon')>Expiring Soon</option>
                    <option value="expired" @selected(request('warranty_status') === 'expired')>Expired</option>
                    <option value="none" @selected(request('warranty_status') === 'none')>No Warranty</option>
                </x-select-input>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <label class="text-xs text-gray-400">Purchased</label>
                <x-text-input type="date" name="purchase_from" class="text-sm" value="{{ request('purchase_from') }}" />
                <span class="text-xs text-gray-400">to</span>
                <x-text-input type="date" name="purchase_to" class="text-sm" value="{{ request('purchase_to') }}" />
                <x-select-input name="sort" class="text-sm">
                    <option value="name" @selected(request('sort', 'name') === 'name')>Sort: Name</option>
                    <option value="asset_code" @selected(request('sort') === 'asset_code')>Sort: Asset ID</option>
                    <option value="purchase_date" @selected(request('sort') === 'purchase_date')>Sort: Purchase Date</option>
                    <option value="status" @selected(request('sort') === 'status')>Sort: Status</option>
                </x-select-input>
                <x-select-input name="direction" class="text-sm">
                    <option value="asc" @selected(request('direction', 'asc') === 'asc')>Ascending</option>
                    <option value="desc" @selected(request('direction') === 'desc')>Descending</option>
                </x-select-input>
                <x-primary-button class="justify-center">Apply Filters</x-primary-button>
                @if (request()->hasAny(['q', 'category', 'status', 'current_location', 'work_order_id', 'brand', 'warranty_status', 'purchase_from', 'purchase_to']))
                    <x-link-button :href="route('assets.index')" variant="secondary">Clear</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :padded="false">
        @if ($assets->isEmpty())
            <div class="p-6"><x-empty-state icon="layers" title="No assets found" description="Adjust your search/filters, or add a new asset." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">S.No</th>
                            <th class="px-4 py-3">Asset</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Condition</th>
                            <th class="px-4 py-3 text-right">Qty</th>
                            <th class="px-4 py-3">Location</th>
                            <th class="px-4 py-3">Warranty</th>
                            @if ($showRemoved)
                                <th class="px-4 py-3"></th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($assets as $asset)
                            <tr @unless($showRemoved) class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('assets.show', $asset) }}'" @endunless>
                                <td class="px-4 py-3 text-gray-500">{{ $assets->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $asset->name }}</p>
                                    <p class="text-xs text-gray-400">{{ $asset->asset_code }} @if($asset->brand) &middot; {{ $asset->brand }} @endif @if($asset->model) {{ $asset->model }} @endif</p>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $asset->category ?: '—' }}</td>
                                <td class="px-4 py-3"><x-badge :status="$asset->status" /></td>
                                <td class="px-4 py-3 text-gray-500">{{ $asset->condition ?: '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ $asset->quantity }}</td>
                                <td class="px-4 py-3 text-gray-500">
                                    @if (request('work_order_id') && $asset->relationLoaded('stocks'))
                                        @php $woQty = $asset->stocks->sum('quantity'); @endphp
                                        {{ $workOrders->firstWhere('id', request('work_order_id'))?->work_order_no }}
                                        <span class="text-xs text-gray-400">&middot; {{ $woQty }} {{ Str::plural('unit', $woQty) }}</span>
                                    @elseif ($asset->current_location === 'work_order' && $asset->currentWorkOrder)
                                        {{ $asset->currentWorkOrder->work_order_no }}
                                    @else
                                        {{ ucwords(str_replace('_', ' ', $asset->current_location)) }}
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-gray-500">
                                    @php $warrantyStatus = $asset->warrantyStatus(); @endphp
                                    @if ($warrantyStatus)
                                        <x-badge :status="$warrantyStatus" />
                                    @else
                                        —
                                    @endif
                                </td>
                                @if ($showRemoved)
                                    <td class="px-4 py-3 text-right whitespace-nowrap" onclick="event.stopPropagation()">
                                        <form method="POST" action="{{ route('assets.restore', $asset->id) }}" class="inline">
                                            @csrf
                                            <button class="text-sm font-medium text-indigo-600 hover:underline">Restore</button>
                                        </form>
                                        <form method="POST" action="{{ route('assets.force-delete', $asset->id) }}" class="inline" onsubmit="return confirm('Permanently delete {{ $asset->asset_code }}? This cannot be undone - all of its movement, repair, and verification history goes with it.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ml-3 text-sm font-medium text-rose-600 hover:underline">Remove</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $assets->links() }}</div>
</x-app-layout>
