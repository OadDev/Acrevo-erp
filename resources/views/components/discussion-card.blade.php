@props([
    'conversation',
    'title' => 'Discussion',
    'subtitle' => 'Internal notes and conversation about this record - not visible to the client.',
    'storeUrl' => null,
    'pollUrl' => null,
    'clearable' => false,
])

<x-card :padded="false">
    <div class="flex items-start justify-between gap-3 border-b border-gray-100 p-4 dark:border-gray-800">
        <div>
            <h3 class="text-sm font-semibold text-gray-500">{{ $title }}</h3>
            <p class="text-xs text-gray-400">{{ $subtitle }}</p>
        </div>
        @if ($clearable)
            <form method="POST" action="{{ route('conversations.clear', $conversation) }}" onsubmit="return confirm('Clear this discussion? All messages and attachments will be permanently removed.')">
                @csrf
                <button type="submit" class="shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-medium text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-500/10">
                    Clear Discussion
                </button>
            </form>
        @endif
    </div>
    <div class="p-3">
        <x-chat-thread :conversation="$conversation" :messages="$conversation->messages" compact :store-url="$storeUrl" :poll-url="$pollUrl" />
    </div>
</x-card>
