<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$ticket->title" :subtitle="$ticket->ticket_no">
            <x-slot name="actions">
                <x-badge :status="$ticket->status" class="text-sm" />
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-400">Work Order</dt><dd>{{ $ticket->workOrder?->work_order_no ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Type</dt><dd><x-badge color="indigo" :status="$ticket->type" /></dd></div>
                    <div><dt class="text-gray-400">Priority</dt><dd><x-badge :status="$ticket->priority" /></dd></div>
                    <div><dt class="text-gray-400">Raised By</dt><dd>{{ $ticket->raisedByName() }}</dd></div>
                    <div><dt class="text-gray-400">Raised On</dt><dd>{{ $ticket->raisedAtIst() }}</dd></div>
                    <div><dt class="text-gray-400">Status</dt><dd><x-badge :status="$ticket->status" /></dd></div>
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
                <form method="POST" action="{{ route('portal.tickets.comments.store', $ticket) }}" class="mb-4 flex gap-2">
                    @csrf
                    <x-text-input name="comment" class="flex-1" placeholder="Add a comment..." required />
                    <x-primary-button>Post</x-primary-button>
                </form>
                <div class="space-y-3">
                    @forelse ($ticket->comments->where('is_internal', false) as $comment)
                        <div class="border-l-2 border-indigo-200 pl-3 text-sm dark:border-indigo-500/30">
                            <p class="text-gray-700 dark:text-gray-300">{{ $comment->comment }}</p>
                            <p class="text-xs text-gray-400">{{ $comment->user?->name ?? 'Team' }} · {{ $comment->created_at->diffForHumans() }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No comments yet.</p>
                    @endforelse
                </div>
            </x-card>
        </div>

        <x-card>
            <h3 class="mb-3 text-sm font-semibold text-gray-500">Status</h3>
            <p class="text-sm text-gray-700 dark:text-gray-300">{{ Str::title(str_replace('_', ' ', $ticket->status)) }}</p>
            @if ($ticket->resolved_at)
                <p class="mt-2 text-xs text-gray-400">Resolved {{ $ticket->resolved_at->diffForHumans() }}</p>
            @endif
            @if ($ticket->closed_at)
                <p class="text-xs text-gray-400">Closed {{ $ticket->closed_at->diffForHumans() }}</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
