@forelse ($messages as $message)
    @php($mine = $message->user_id === $user->id)
    <div class="mb-3 flex {{ $mine ? 'justify-end' : 'justify-start' }}" data-message-id="{{ $message->id }}">
        <div class="max-w-[75%] rounded-2xl px-4 py-2 text-sm {{ $mine ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200' }}">
            @unless ($mine)
                <p class="mb-0.5 text-xs font-semibold text-indigo-600 dark:text-indigo-400">{{ $message->user?->name ?? 'Deleted user' }}</p>
            @endunless
            @if ($message->body)
                <p class="whitespace-pre-line">{{ $message->body }}</p>
            @endif
            @if ($message->media->isNotEmpty())
                <div class="mt-2 space-y-1">
                    @foreach ($message->media as $file)
                        <a href="{{ $file->getUrl() }}" target="_blank" class="flex items-center gap-1.5 rounded-lg {{ $mine ? 'bg-indigo-500/40' : 'bg-white dark:bg-gray-700' }} px-2 py-1.5 text-xs">
                            <x-icon name="paperclip" class="h-3.5 w-3.5 shrink-0" />
                            <span class="truncate">{{ $file->file_name }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
            <p class="mt-1 text-right text-[10px] {{ $mine ? 'text-indigo-200' : 'text-gray-400' }}">{{ $message->created_at->timezone('Asia/Kolkata')->format('h:i A, d M') }}</p>
        </div>
    </div>
@empty
    @if (! request()->filled('after'))
        <p class="py-10 text-center text-sm text-gray-400">No messages yet. Say hello!</p>
    @endif
@endforelse
