<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$ticket->ticket_no" :subtitle="$ticket->title">
            <x-slot name="actions">
                <x-badge :status="$ticket->status" class="text-sm" />
                @if ($ticket->locked_at)
                    <x-badge status="closed" color="rose">Uneditable</x-badge>
                @endif
                <x-link-button :href="route('tickets.pdf', $ticket)" variant="secondary">PDF</x-link-button>
                @if ($ticket->media->isNotEmpty())
                    <x-link-button :href="route('tickets.zip', $ticket)" variant="secondary">ZIP</x-link-button>
                @endif
                @can('update', $ticket)
                    <x-link-button :href="route('tickets.edit', $ticket)" variant="secondary">Edit</x-link-button>
                @endcan
                @can('tickets.manage')
                    <form method="POST" action="{{ route('tickets.destroy', $ticket) }}" onsubmit="return confirm('Remove this ticket? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Remove</button>
                    </form>
                @endcan
                @can('lock', $ticket)
                    <form method="POST" action="{{ route('tickets.lock', $ticket) }}" onsubmit="return confirm('Mark this ticket uneditable? This cannot be undone.')">
                        @csrf
                        <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Mark Uneditable</button>
                    </form>
                @endcan
                @can('tickets.manage')
                    <form method="POST" action="{{ route('tickets.status', $ticket) }}" class="flex items-center gap-2">
                        @csrf
                        <x-select-input name="status" onchange="this.form.submit()" class="text-sm">
                            @foreach (['open', 'in_progress', 'resolved', 'closed'] as $status)
                                <option value="{{ $status }}" @selected($ticket->status === $status)>{{ Str::title(str_replace('_',' ',$status)) }}</option>
                            @endforeach
                        </x-select-input>
                    </form>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-400">Work Order</dt><dd>
                        @if ($ticket->workOrder)
                            <a href="{{ route('work-orders.show', $ticket->workOrder) }}" class="text-indigo-600 hover:underline">{{ $ticket->workOrder->work_order_no }}</a>
                        @else
                            <span class="text-gray-400">Deleted work order</span>
                        @endif
                    </dd></div>
                    <div><dt class="text-gray-400">Type</dt><dd><x-badge color="indigo" :status="$ticket->type" /></dd></div>
                    <div><dt class="text-gray-400">Priority</dt><dd><x-badge :status="$ticket->priority" /></dd></div>
                    <div><dt class="text-gray-400">Department</dt><dd>{{ $ticket->department?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Assigned To</dt><dd>{{ $ticket->assignedTo?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Due Date</dt><dd>{{ optional($ticket->due_date)->format('d M Y') ?? '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-400">Description</dt><dd>{{ $ticket->description ?: '—' }}</dd></div>
                </dl>

                @if ($ticket->media->isNotEmpty())
                    <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Attachments</p>
                        <div class="flex flex-wrap gap-3">
                            @foreach ($ticket->media as $file)
                                <a href="{{ $file->getUrl() }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-indigo-600 hover:underline dark:border-gray-700">
                                    <x-icon name="file-text" class="h-4 w-4" /> {{ $file->file_name }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-card>

            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Comments</h3>
                <form method="POST" action="{{ route('tickets.comments.store', $ticket) }}" class="mb-4 flex gap-2">
                    @csrf
                    <x-text-input name="comment" class="flex-1" placeholder="Add a comment..." required />
                    <x-primary-button>Post</x-primary-button>
                </form>
                <div class="space-y-3">
                    @forelse ($ticket->comments as $comment)
                        <div class="border-l-2 border-indigo-200 pl-3 text-sm dark:border-indigo-500/30">
                            <p class="text-gray-700 dark:text-gray-300">{{ $comment->comment }}</p>
                            <p class="text-xs text-gray-400">{{ $comment->user?->name ?? 'Client' }} · {{ $comment->created_at->diffForHumans() }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No comments yet.</p>
                    @endforelse
                </div>
            </x-card>
        </div>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold text-gray-500">Raised By</h3>
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ $ticket->raisedBy?->name ?? 'Client' }}</p>
            <p class="text-xs text-gray-400">{{ $ticket->created_at->diffForHumans() }}</p>
        </x-card>
    </div>
</x-app-layout>
