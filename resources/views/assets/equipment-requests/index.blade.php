<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Equipment Requests" subtitle="Requested &rarr; Approved &rarr; Purchase Required/Available &rarr; Dispatched &rarr; Received &rarr; Completed.">
            <x-slot name="actions">
                @can('equipment_requests.download_pdf')
                    <x-link-button :href="route('equipment-requests.pdf', request()->query())" variant="secondary">Download PDF</x-link-button>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    @can('equipment_requests.create')
        <x-card class="mb-4">
            <h3 class="mb-3 text-sm font-semibold text-gray-500">New Equipment Request</h3>
            <form method="POST" action="{{ route('equipment-requests.store') }}" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <x-text-input name="item_name" class="text-sm" placeholder="Item name" required />
                <x-text-input name="category" class="text-sm" placeholder="Category" />
                <x-text-input type="number" name="quantity" class="text-sm" value="1" min="1" placeholder="Quantity" required />
                <x-select-input name="work_order_id" class="text-sm">
                    <option value="">Company Store / No Specific Site</option>
                    @foreach ($workOrders as $wo)
                        <option value="{{ $wo->id }}">{{ $wo->work_order_no }}</option>
                    @endforeach
                </x-select-input>
                <x-text-input type="date" name="required_by_date" class="text-sm" placeholder="Required By" />
                <x-textarea-input name="reason" rows="1" class="text-sm sm:col-span-2 lg:col-span-3" placeholder="Reason"></x-textarea-input>
                <x-primary-button class="justify-center">Submit Request</x-primary-button>
            </form>
        </x-card>
    @endcan

    <x-card class="mb-4">
        <form method="GET" action="{{ route('equipment-requests.index') }}" class="space-y-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by item name or category..." class="w-full rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-select-input name="status" class="text-sm">
                    <option value="">All Statuses</option>
                    @foreach (\App\Models\EquipmentRequest::STATUSES as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="work_order_id" class="text-sm">
                    <option value="">Any Work Order</option>
                    @foreach ($workOrders as $wo)
                        <option value="{{ $wo->id }}" @selected(request('work_order_id') == $wo->id)>{{ $wo->work_order_no }}</option>
                    @endforeach
                </x-select-input>
                <x-text-input type="date" name="from" class="text-sm" value="{{ request('from') }}" placeholder="From" />
                <x-text-input type="date" name="to" class="text-sm" value="{{ request('to') }}" placeholder="To" />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-primary-button class="justify-center">Apply Filters</x-primary-button>
                @if (request()->hasAny(['q', 'status', 'work_order_id', 'from', 'to']))
                    <x-link-button :href="route('equipment-requests.index')" variant="secondary">Clear</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :padded="false">
        @if ($equipmentRequests->isEmpty())
            <div class="p-6"><x-empty-state icon="inbox" title="No equipment requests found" description="Adjust your search/filters." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">Requested</th>
                            <th class="px-4 py-3">Item</th>
                            <th class="px-4 py-3">Work Order</th>
                            <th class="px-4 py-3">Requested By</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3">Asset</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($equipmentRequests as $equipmentRequest)
                            <tr>
                                <td class="px-4 py-3 text-gray-500">{{ $equipmentRequest->created_at->format('d M Y') }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $equipmentRequest->item_name }} @if($equipmentRequest->quantity > 1)<span class="text-xs text-gray-400">&times;{{ $equipmentRequest->quantity }}</span>@endif</p>
                                    <p class="text-xs text-gray-400">{{ $equipmentRequest->category ?: '—' }}</p>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $equipmentRequest->workOrder?->work_order_no ?? 'Company Store' }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $equipmentRequest->requestedBy?->name }}</td>
                                <td class="px-4 py-3"><x-badge :status="$equipmentRequest->status" /></td>
                                <td class="px-4 py-3 text-gray-500">{{ $equipmentRequest->asset?->asset_code ?? '—' }}</td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    @can('equipment_requests.approve')
                                        @if ($equipmentRequest->status === 'requested')
                                            <form method="POST" action="{{ route('equipment-requests.approve', $equipmentRequest) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="decision" value="approved">
                                                <input type="hidden" name="needs_purchase" value="0">
                                                <button class="text-xs font-medium text-emerald-600 hover:underline">Approve (In Stock)</button>
                                            </form>
                                            <form method="POST" action="{{ route('equipment-requests.approve', $equipmentRequest) }}" class="inline">
                                                @csrf
                                                <input type="hidden" name="decision" value="approved">
                                                <input type="hidden" name="needs_purchase" value="1">
                                                <button class="ml-2 text-xs font-medium text-amber-600 hover:underline">Approve (Needs Purchase)</button>
                                            </form>
                                            <form method="POST" action="{{ route('equipment-requests.approve', $equipmentRequest) }}" class="inline" onsubmit="return confirm('Reject this request?')">
                                                @csrf
                                                <input type="hidden" name="decision" value="rejected">
                                                <input type="hidden" name="rejection_reason" value="Not approved.">
                                                <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Reject</button>
                                            </form>
                                        @endif
                                        @if ($equipmentRequest->status === 'purchase_required')
                                            <form method="POST" action="{{ route('equipment-requests.mark-available', $equipmentRequest) }}" class="inline">
                                                @csrf
                                                <button class="text-xs font-medium text-indigo-600 hover:underline">Mark Available</button>
                                            </form>
                                        @endif
                                        @if ($equipmentRequest->status === 'available')
                                            <form method="POST" action="{{ route('equipment-requests.dispatch', $equipmentRequest) }}" class="inline">
                                                @csrf
                                                <button class="text-xs font-medium text-indigo-600 hover:underline">Dispatch</button>
                                            </form>
                                        @endif
                                    @endcan
                                    @can('equipment_requests.receive')
                                        @if ($equipmentRequest->status === 'dispatched')
                                            <form method="POST" action="{{ route('equipment-requests.receive', $equipmentRequest) }}" class="inline">
                                                @csrf
                                                <button class="text-xs font-medium text-emerald-600 hover:underline">Confirm Receipt</button>
                                            </form>
                                        @endif
                                        @if ($equipmentRequest->status === 'received')
                                            <form method="POST" action="{{ route('equipment-requests.complete', $equipmentRequest) }}" class="inline">
                                                @csrf
                                                <button class="text-xs font-medium text-emerald-600 hover:underline">Mark Completed</button>
                                            </form>
                                        @endif
                                    @endcan
                                    @can('equipment_requests.create')
                                        @if (! in_array($equipmentRequest->status, ['dispatched', 'received', 'completed', 'cancelled']) && ($equipmentRequest->requested_by === auth()->id() || auth()->user()->hasRole('Admin')))
                                            <form method="POST" action="{{ route('equipment-requests.cancel', $equipmentRequest) }}" class="inline" onsubmit="return confirm('Cancel this request?')">
                                                @csrf
                                                <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Cancel</button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $equipmentRequests->links() }}</div>
</x-app-layout>
