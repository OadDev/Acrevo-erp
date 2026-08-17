<div class="grid grid-cols-1 gap-6 lg:grid-cols-3" x-data="{ editApproval: null }">
    <div class="space-y-3 lg:col-span-2">
        @forelse ($workOrder->approvalRequests as $approval)
            <x-card>
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $approval->approval_no }} — {{ $approval->title }}</p>
                        <p class="text-xs text-gray-400">
                            {{ $approval->direction === 'company_to_client' ? 'Sent to client for approval' : 'Client requesting our approval' }}
                            · {{ $approval->requestedBy?->name ?? $approval->requestedByClient?->name ?? 'Unknown' }}
                            · {{ $approval->created_at->format('d M Y') }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <x-badge :status="$approval->status" />
                        @if (auth()->user()->hasRole('Admin'))
                            <button type="button" @click="editApproval === '{{ $approval->id }}' ? editApproval = null : editApproval = '{{ $approval->id }}'" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                            <form method="POST" action="{{ route('work-orders.approval-requests.destroy', [$workOrder, $approval]) }}" onsubmit="return confirm('Remove this approval request?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs font-medium text-rose-600 hover:underline">Delete</button>
                            </form>
                        @endif
                    </div>
                </div>

                @if (auth()->user()->hasRole('Admin'))
                    <form method="POST" action="{{ route('work-orders.approval-requests.update', [$workOrder, $approval]) }}" x-show="editApproval === '{{ $approval->id }}'" x-cloak class="mt-2 space-y-2 rounded-lg border border-gray-100 p-2.5 dark:border-gray-800">
                        @csrf
                        @method('PUT')
                        <x-text-input name="title" value="{{ $approval->title }}" class="w-full text-xs" required />
                        <x-textarea-input name="description" rows="2" class="w-full text-xs">{{ $approval->description }}</x-textarea-input>
                        <button class="w-full rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Save</button>
                    </form>
                @endif

                @if ($approval->description)
                    <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">{{ $approval->description }}</p>
                @endif

                @if ($approval->getFirstMedia('attachment'))
                    <a href="{{ $approval->getFirstMediaUrl('attachment') }}" target="_blank" class="mt-2 inline-flex items-center gap-1.5 text-sm text-indigo-600 hover:underline">
                        <x-icon name="paperclip" class="h-4 w-4" /> {{ $approval->getFirstMedia('attachment')->file_name }}
                    </a>
                @endif

                @if ($approval->status !== 'pending')
                    <div class="mt-3 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-sm dark:border-gray-800 dark:bg-gray-900">
                        <p class="font-medium text-gray-700 dark:text-gray-300">{{ Str::title($approval->status) }} by {{ $approval->respondedBy?->name ?? '—' }}</p>
                        @if ($approval->response_note)
                            <p class="text-gray-500">{{ $approval->response_note }}</p>
                        @endif
                    </div>
                @elseif ($approval->direction === 'client_to_company')
                    @can('work_orders.edit')
                        <form method="POST" action="{{ route('work-orders.approval-requests.respond', [$workOrder, $approval]) }}" class="mt-3 flex flex-wrap items-end gap-2 border-t border-gray-100 pt-3 dark:border-gray-800">
                            @csrf
                            <div class="flex-1">
                                <x-input-label value="Response Note (optional)" />
                                <x-text-input name="response_note" class="mt-1 w-full" />
                            </div>
                            <button name="status" value="approved" class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500">Approve</button>
                            <button name="status" value="rejected" class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Reject</button>
                        </form>
                    @endcan
                @else
                    <p class="mt-3 border-t border-gray-100 pt-3 text-xs text-gray-400 dark:border-gray-800">Awaiting the client's response.</p>
                @endif
            </x-card>
        @empty
            <x-empty-state icon="file-text" title="No approval requests yet" />
        @endforelse
    </div>

    @can('work_orders.edit')
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Request Client Approval</h3>
            <form method="POST" action="{{ route('work-orders.approval-requests.store', $workOrder) }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <x-input-label value="Title" />
                    <x-text-input name="title" class="mt-1 w-full" required />
                </div>
                <div>
                    <x-input-label value="Description" />
                    <x-textarea-input name="description" rows="3" class="mt-1 w-full"></x-textarea-input>
                </div>
                <div>
                    <x-input-label value="Attachment (image, PDF, or Word doc)" />
                    <input type="file" name="file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="mt-1 w-full text-sm">
                </div>
                <x-primary-button class="w-full justify-center">Send to Client</x-primary-button>
            </form>
        </x-card>
    @endcan
</div>
