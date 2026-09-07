<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$asset->name" :subtitle="$asset->asset_code">
            <x-slot name="actions">
                <x-badge :status="$asset->status" class="text-sm" />
                @can('assets.download_pdf')
                    <x-link-button :href="route('assets.pdf', $asset)" variant="secondary">Download PDF</x-link-button>
                @endcan
                @can('movements.download_pdf')
                    <x-link-button :href="route('assets.movements.pdf', $asset)" variant="secondary">Movement History PDF</x-link-button>
                @endcan
                @can('repairs.download_pdf')
                    <x-link-button :href="route('assets.repairs.pdf', $asset)" variant="secondary">Repair History PDF</x-link-button>
                @endcan
                @can('verifications.download_pdf')
                    <x-link-button :href="route('assets.verifications.pdf', $asset)" variant="secondary">Verification History PDF</x-link-button>
                @endcan
                @can('assets.edit')
                    <x-link-button :href="route('assets.edit', $asset)" variant="secondary">Edit</x-link-button>
                @endcan
                @can('assets.delete')
                    <form method="POST" action="{{ route('assets.destroy', $asset) }}" onsubmit="return confirm('Remove {{ $asset->asset_code }}? An Admin can restore it later from the Removed Assets list.')">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Remove</button>
                    </form>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Asset Details</h3>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-400">Category</dt><dd class="text-gray-800 dark:text-gray-200">{{ $asset->category ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400">Brand / Model</dt><dd class="text-gray-800 dark:text-gray-200">{{ collect([$asset->brand, $asset->model])->filter()->join(' / ') ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400">Serial Number</dt><dd class="text-gray-800 dark:text-gray-200">{{ $asset->serial_number ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400">Condition</dt><dd class="text-gray-800 dark:text-gray-200">{{ $asset->condition ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400">Current Location</dt>
                        <dd class="text-gray-800 dark:text-gray-200">
                            @if ($asset->current_location === 'work_order' && $asset->currentWorkOrder)
                                <a href="{{ route('work-orders.show', $asset->currentWorkOrder) }}" class="text-indigo-600 hover:underline">{{ $asset->currentWorkOrder->work_order_no }}</a>
                            @else
                                {{ ucwords(str_replace('_', ' ', $asset->current_location)) }}
                            @endif
                        </dd>
                    </div>
                    <div class="col-span-2"><dt class="text-gray-400">Remarks</dt><dd class="text-gray-800 dark:text-gray-200">{{ $asset->remarks ?: '—' }}</dd></div>
                </dl>
            </x-card>

            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Purchase Details</h3>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-400">Purchase Date</dt><dd class="text-gray-800 dark:text-gray-200">{{ optional($asset->purchase_date)->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Purchase Cost</dt><dd class="text-gray-800 dark:text-gray-200">{{ $asset->purchase_cost !== null ? 'Rs. '.number_format($asset->purchase_cost, 2) : '—' }}</dd></div>
                    <div><dt class="text-gray-400">Supplier</dt><dd class="text-gray-800 dark:text-gray-200">{{ $asset->supplier ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400">Invoice Number</dt><dd class="text-gray-800 dark:text-gray-200">{{ $asset->invoice_number ?: '—' }}</dd></div>
                </dl>
            </x-card>

            <x-card>
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-sm font-semibold text-gray-500">Warranty Details</h3>
                    @if ($asset->warrantyStatus())
                        <x-badge :status="$asset->warrantyStatus()" />
                    @endif
                </div>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-400">Warranty Start</dt><dd class="text-gray-800 dark:text-gray-200">{{ optional($asset->warranty_start)->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Warranty End</dt><dd class="text-gray-800 dark:text-gray-200">{{ optional($asset->warranty_end)->format('d M Y') ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Provider</dt><dd class="text-gray-800 dark:text-gray-200">{{ $asset->warranty_provider ?: '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-400">Warranty Card Details</dt><dd class="text-gray-800 dark:text-gray-200">{{ $asset->warranty_card_details ?: '—' }}</dd></div>
                </dl>
            </x-card>

            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Attachments</h3>
                @php $attachments = $asset->getMedia('attachments'); @endphp
                @forelse ($attachments as $file)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <a href="{{ $file->getUrl() }}" target="_blank" class="text-indigo-600 hover:underline">{{ $file->file_name }}</a>
                        @can('assets.edit')
                            <form method="POST" action="{{ route('assets.media.destroy', [$asset, $file]) }}" onsubmit="return confirm('Remove this file?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs font-medium text-rose-600 hover:underline">Remove</button>
                            </form>
                        @endcan
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No attachments uploaded.</p>
                @endforelse
            </x-card>

            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Reports</h3>
                @php $reports = $asset->getMedia('reports'); @endphp
                @forelse ($reports as $file)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <a href="{{ $file->getUrl() }}" target="_blank" class="text-indigo-600 hover:underline">{{ $file->file_name }}</a>
                        @can('assets.edit')
                            <form method="POST" action="{{ route('assets.media.destroy', [$asset, $file]) }}" onsubmit="return confirm('Remove this file?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs font-medium text-rose-600 hover:underline">Remove</button>
                            </form>
                        @endcan
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No reports uploaded.</p>
                @endforelse
            </x-card>

            @can('movements.view')
                <x-card :padded="false">
                    <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Movement History</h3></div>
                    @if ($asset->movements->isEmpty())
                        <p class="px-4 pb-4 text-sm text-gray-400">No movements recorded yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-2">Date</th>
                                        <th class="px-4 py-2">From</th>
                                        <th class="px-4 py-2">To</th>
                                        <th class="px-4 py-2">Type</th>
                                        <th class="px-4 py-2">Status</th>
                                        <th class="px-4 py-2">Remarks</th>
                                        <th class="px-4 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($asset->movements as $movement)
                                        @php $canConfirmThis = $ledWorkOrderIds === null || $ledWorkOrderIds->contains($movement->to_work_order_id); @endphp
                                        <tr>
                                            <td class="px-4 py-2 text-gray-500">{{ $movement->moved_at->format('d M Y') }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $movement->locationLabel($movement->from_location, $movement->fromWorkOrder) }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $movement->locationLabel($movement->to_location, $movement->toWorkOrder) }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ ucwords(str_replace('_', ' ', $movement->type)) }}</td>
                                            <td class="px-4 py-2"><x-badge :status="$movement->status" /></td>
                                            <td class="px-4 py-2 text-gray-500">{{ $movement->remarks ?: '—' }}</td>
                                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                                @if ($movement->status === 'pending')
                                                    @can('movements.edit')
                                                        <a href="{{ route('asset-movements.edit', $movement) }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</a>
                                                    @endcan
                                                    @can('movements.approve')
                                                        @if ($canConfirmThis)
                                                            <form method="POST" action="{{ route('asset-movements.confirm', $movement) }}" class="inline">
                                                                @csrf
                                                                <button class="text-xs font-medium text-emerald-600 hover:underline">Confirm</button>
                                                            </form>
                                                            <form method="POST" action="{{ route('asset-movements.cancel', $movement) }}" class="inline" onsubmit="return confirm('Cancel this movement?')">
                                                                @csrf
                                                                <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Cancel</button>
                                                            </form>
                                                        @endif
                                                    @endcan
                                                @endif
                                                @can('movements.delete')
                                                    <form method="POST" action="{{ route('asset-movements.destroy', $movement) }}" class="inline" onsubmit="return confirm('Remove this movement entry? This cannot be undone.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Remove</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            @endcan

            @can('repairs.view')
                <x-card :padded="false">
                    <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Repair History</h3></div>
                    @if ($asset->repairs->isEmpty())
                        <p class="px-4 pb-4 text-sm text-gray-400">No repairs recorded yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-2">Reported</th>
                                        <th class="px-4 py-2">Type</th>
                                        <th class="px-4 py-2">Issue</th>
                                        <th class="px-4 py-2">Technician / Vendor</th>
                                        <th class="px-4 py-2">Warranty</th>
                                        <th class="px-4 py-2 text-right">Cost</th>
                                        <th class="px-4 py-2">Status</th>
                                        <th class="px-4 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($asset->repairs as $repair)
                                        @php $canUpdateThisRepair = $ledWorkOrderIds === null || $ledWorkOrderIds->contains($repair->work_order_id); @endphp
                                        <tr>
                                            <td class="px-4 py-2 text-gray-500">{{ $repair->reported_date->format('d M Y') }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ ucwords($repair->repair_type) }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $repair->issue_description }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $repair->technician_vendor ?: '—' }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $repair->is_warranty_repair ? 'Warranty' : 'Paid' }}</td>
                                            <td class="px-4 py-2 text-right text-gray-500">{{ $repair->cost !== null ? 'Rs. '.number_format($repair->cost, 2) : '—' }}</td>
                                            <td class="px-4 py-2"><x-badge :status="$repair->status" /></td>
                                            <td class="px-4 py-2 text-right whitespace-nowrap">
                                                @can('repairs.update_status')
                                                    @if ($canUpdateThisRepair && ! in_array($repair->status, ['completed', 'cancelled']))
                                                        <form method="POST" action="{{ route('asset-repairs.status.update', $repair) }}" class="inline-flex items-center gap-1">
                                                            @csrf
                                                            <select name="status" class="rounded-lg border-gray-200 text-xs dark:border-gray-700 dark:bg-gray-800" onchange="this.form.submit()">
                                                                <option value="">Update status…</option>
                                                                @foreach (\App\Models\AssetRepair::STATUSES as $status)
                                                                    @continue($status === $repair->status)
                                                                    <option value="{{ $status }}">{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                                                                @endforeach
                                                            </select>
                                                        </form>
                                                    @endif
                                                @endcan
                                                @can('repairs.edit')
                                                    <a href="{{ route('asset-repairs.edit', $repair) }}" class="ml-2 text-xs font-medium text-indigo-600 hover:underline">Edit</a>
                                                @endcan
                                                @can('repairs.delete')
                                                    <form method="POST" action="{{ route('asset-repairs.destroy', $repair) }}" class="inline" onsubmit="return confirm('Remove this repair entry? This cannot be undone.')">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Remove</button>
                                                    </form>
                                                @endcan
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            @endcan

            @can('verifications.view')
                <x-card :padded="false">
                    <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Verification History</h3></div>
                    @if ($asset->verifications->isEmpty())
                        <p class="px-4 pb-4 text-sm text-gray-400">No verifications recorded yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-2">Verified On</th>
                                        <th class="px-4 py-2">Result</th>
                                        <th class="px-4 py-2">Condition</th>
                                        <th class="px-4 py-2">Verified By</th>
                                        <th class="px-4 py-2">Remarks</th>
                                        <th class="px-4 py-2">Proof</th>
                                        @canany(['verifications.edit', 'verifications.delete'])
                                            <th class="px-4 py-2"></th>
                                        @endcanany
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($asset->verifications as $verification)
                                        <tr>
                                            <td class="px-4 py-2 text-gray-500">{{ $verification->verified_at->format('d M Y') }}</td>
                                            <td class="px-4 py-2"><x-badge :status="$verification->result" /></td>
                                            <td class="px-4 py-2 text-gray-500">{{ $verification->condition ?: '—' }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $verification->verifiedBy?->name }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $verification->remarks ?: '—' }}</td>
                                            <td class="px-4 py-2">
                                                @foreach ($verification->getMedia('proof') as $proof)
                                                    <a href="{{ $proof->getUrl() }}" target="_blank" class="text-xs text-indigo-600 hover:underline">View</a>
                                                @endforeach
                                            </td>
                                            @canany(['verifications.edit', 'verifications.delete'])
                                                <td class="px-4 py-2 text-right whitespace-nowrap">
                                                    @can('verifications.edit')
                                                        <a href="{{ route('asset-verifications.edit', $verification) }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</a>
                                                    @endcan
                                                    @can('verifications.delete')
                                                        <form method="POST" action="{{ route('asset-verifications.destroy', $verification) }}" class="inline" onsubmit="return confirm('Remove this verification entry? This cannot be undone.')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Remove</button>
                                                        </form>
                                                    @endcan
                                                </td>
                                            @endcanany
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            @endcan

            @can('assets.view_history')
                <x-card :padded="false">
                    <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Status History</h3></div>
                    @if ($asset->statusLogs->isEmpty())
                        <p class="px-4 pb-4 text-sm text-gray-400">No status changes recorded yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-2">Date</th>
                                        <th class="px-4 py-2">Change</th>
                                        <th class="px-4 py-2">Updated By</th>
                                        <th class="px-4 py-2">Site / Work Order</th>
                                        <th class="px-4 py-2">Reason / Remarks</th>
                                        <th class="px-4 py-2">Proof</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($asset->statusLogs as $log)
                                        <tr>
                                            <td class="px-4 py-2 text-gray-500">{{ $log->created_at->format('d M Y, h:i A') }}</td>
                                            <td class="px-4 py-2"><x-badge :status="$log->previous_status" /> &rarr; <x-badge :status="$log->new_status" /></td>
                                            <td class="px-4 py-2 text-gray-500">{{ $log->updatedBy?->name }} @if($log->role)<span class="text-xs text-gray-400">({{ $log->role }})</span>@endif</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $log->workOrder?->work_order_no ?? '—' }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $log->reason ?: '—' }}</td>
                                            <td class="px-4 py-2">
                                                @foreach ($log->getMedia('proof') as $proof)
                                                    <a href="{{ $proof->getUrl() }}" target="_blank" class="text-xs text-indigo-600 hover:underline">View</a>
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>

                <x-card :padded="false">
                    <div class="p-4"><h3 class="text-sm font-semibold text-gray-500">Change Requests</h3></div>
                    @if ($asset->changeRequests->isEmpty())
                        <p class="px-4 pb-4 text-sm text-gray-400">No edit requests submitted yet.</p>
                    @else
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th class="px-4 py-2">Requested By</th>
                                        <th class="px-4 py-2">Date</th>
                                        <th class="px-4 py-2">Status</th>
                                        <th class="px-4 py-2">Reviewed By</th>
                                        <th class="px-4 py-2">Remarks</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($asset->changeRequests as $changeRequest)
                                        <tr>
                                            <td class="px-4 py-2 text-gray-500">{{ $changeRequest->requestedBy?->name }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $changeRequest->created_at->format('d M Y, h:i A') }}</td>
                                            <td class="px-4 py-2"><x-badge :status="$changeRequest->status" /></td>
                                            <td class="px-4 py-2 text-gray-500">{{ $changeRequest->reviewedBy?->name ?? '—' }}</td>
                                            <td class="px-4 py-2 text-gray-500">{{ $changeRequest->remarks ?: '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </x-card>
            @endcan
        </div>

        <div class="space-y-6">
            @if ($canUpdateStatus)
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Update Status</h3>
                    <form method="POST" action="{{ route('assets.status.update', $asset) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <x-select-input name="status" class="w-full text-sm" required>
                            @foreach (\App\Models\Asset::STATUSES as $status)
                                <option value="{{ $status }}" @selected($status === $asset->status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                            @endforeach
                        </x-select-input>
                        <x-textarea-input name="reason" rows="2" class="w-full text-sm" placeholder="Reason / remarks"></x-textarea-input>
                        <div>
                            <label class="text-xs text-gray-400">Supporting photo / document (optional)</label>
                            <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block w-full text-sm">
                        </div>
                        <x-primary-button class="w-full justify-center">Save Status</x-primary-button>
                    </form>
                </x-card>
            @endif

            @if ($canCreateMovement)
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">New Movement</h3>
                    <form method="POST" action="{{ route('assets.movements.store', $asset) }}" class="space-y-3" x-data="{ toLocation: 'work_order' }">
                        @csrf
                        <x-select-input name="type" class="w-full text-sm" required>
                            @foreach (\App\Models\AssetMovement::TYPES as $type)
                                @continue($type === 'purchase')
                                <option value="{{ $type }}">{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                            @endforeach
                        </x-select-input>
                        <x-select-input name="to_location" class="w-full text-sm" x-model="toLocation" required>
                            @foreach (\App\Models\AssetMovement::LOCATIONS as $location)
                                <option value="{{ $location }}">{{ ucwords(str_replace('_', ' ', $location)) }}</option>
                            @endforeach
                        </x-select-input>
                        <div x-show="toLocation === 'work_order'">
                            <x-select-input name="to_work_order_id" class="w-full text-sm">
                                <option value="">Select Work Order</option>
                                @foreach (\App\Models\WorkOrder::orderByDesc('created_at')->limit(200)->get() as $wo)
                                    <option value="{{ $wo->id }}">{{ $wo->work_order_no }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        <x-text-input type="date" name="moved_at" class="w-full text-sm" value="{{ now()->format('Y-m-d') }}" required />
                        <x-textarea-input name="remarks" rows="2" class="w-full text-sm" placeholder="Remarks"></x-textarea-input>
                        <x-primary-button class="w-full justify-center">Record Movement</x-primary-button>
                    </form>
                </x-card>
            @endif

            @if ($canCreateVerification)
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Verify Asset</h3>
                    <form method="POST" action="{{ route('assets.verifications.store', $asset) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <x-select-input name="result" class="w-full text-sm" required>
                            @foreach (\App\Models\AssetVerification::RESULTS as $result)
                                <option value="{{ $result }}">{{ ucwords(str_replace('_', ' ', $result)) }}</option>
                            @endforeach
                        </x-select-input>
                        <x-text-input name="condition" class="w-full text-sm" placeholder="Condition observed" />
                        <x-text-input type="date" name="verified_at" class="w-full text-sm" value="{{ now()->format('Y-m-d') }}" required />
                        <x-textarea-input name="remarks" rows="2" class="w-full text-sm" placeholder="Remarks"></x-textarea-input>
                        <div>
                            <label class="text-xs text-gray-400">Supporting photo / document (optional)</label>
                            <input type="file" name="proof" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 block w-full text-sm">
                        </div>
                        <x-primary-button class="w-full justify-center">Record Verification</x-primary-button>
                    </form>
                </x-card>
            @endif

            @if ($canCreateRepair)
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Report a Repair</h3>
                    <form method="POST" action="{{ route('assets.repairs.store', $asset) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <x-select-input name="repair_type" class="w-full text-sm" required>
                            @foreach (\App\Models\AssetRepair::TYPES as $type)
                                <option value="{{ $type }}">{{ ucwords($type) }}</option>
                            @endforeach
                        </x-select-input>
                        <x-textarea-input name="issue_description" rows="2" class="w-full text-sm" placeholder="Issue description" required></x-textarea-input>
                        <x-text-input name="technician_vendor" class="w-full text-sm" placeholder="Technician / Vendor" />
                        <label class="flex items-center gap-2 text-xs text-gray-500">
                            <input type="checkbox" name="is_warranty_repair" value="1" class="rounded border-gray-300">
                            Warranty repair
                        </label>
                        <x-text-input type="number" step="0.01" name="cost" class="w-full text-sm" placeholder="Cost (if known)" />
                        <x-text-input type="date" name="reported_date" class="w-full text-sm" value="{{ now()->format('Y-m-d') }}" required />
                        <x-textarea-input name="remarks" rows="2" class="w-full text-sm" placeholder="Remarks"></x-textarea-input>
                        <div>
                            <label class="text-xs text-gray-400">Attachments (optional)</label>
                            <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.mp4,.mov,.avi" class="mt-1 block w-full text-sm">
                        </div>
                        <x-primary-button class="w-full justify-center">Report Repair</x-primary-button>
                    </form>
                </x-card>
            @endif

            <x-card>
                <h3 class="mb-3 text-sm font-semibold text-gray-500">Record</h3>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-400">Created By</dt><dd class="text-gray-800 dark:text-gray-200">{{ $asset->createdBy?->name }}</dd></div>
                    <div><dt class="text-gray-400">Created On</dt><dd class="text-gray-800 dark:text-gray-200">{{ $asset->created_at->format('d M Y') }}</dd></div>
                </dl>
            </x-card>
        </div>
    </div>
</x-app-layout>
