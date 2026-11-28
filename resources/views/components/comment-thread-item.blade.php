@props(['comment', 'updateUrl', 'destroyUrl', 'mediaDestroyUrls' => [], 'clientLabel' => 'Client'])

<div class="border-l-2 border-indigo-200 pl-3 text-sm dark:border-indigo-500/30" x-data="{ editing: false }">
    <div x-show="!editing">
        <p class="whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $comment->comment }}</p>

        @if ($comment->getMedia('attachments')->isNotEmpty())
            <div class="mt-1 flex flex-wrap gap-2">
                @foreach ($comment->getMedia('attachments') as $attachment)
                    <span class="inline-flex items-center gap-1 text-xs text-indigo-600">
                        <a href="{{ $attachment->getUrl() }}" target="_blank" class="inline-flex items-center gap-1 hover:underline">
                            <x-icon name="paperclip" class="h-3 w-3" /> {{ $attachment->file_name }}
                        </a>
                        @can('deleteAttachment', $comment)
                            <form method="POST" action="{{ $mediaDestroyUrls[$attachment->id] ?? '#' }}" onsubmit="return confirm('Remove this attachment?')" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-rose-500 hover:underline" title="Remove attachment">&times;</button>
                            </form>
                        @endcan
                    </span>
                @endforeach
            </div>
        @endif

        <p class="mt-1 text-xs text-gray-400">
            {{ $comment->user?->name ?? $clientLabel }}
            @if ($comment->user?->hasRole('Client'))
                <span class="text-indigo-500">(Client)</span>
            @endif
            · {{ $comment->created_at->diffForHumans() }}
            @if ($comment->isEdited())
                <span class="italic">(edited)</span>
            @endif
            @can('update', $comment)
                · <button type="button" x-on:click="editing = true" class="font-medium text-indigo-600 hover:underline">Edit</button>
            @endcan
            @can('delete', $comment)
                · <form method="POST" action="{{ $destroyUrl }}" onsubmit="return confirm('Delete this comment? This cannot be undone.')" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="font-medium text-rose-600 hover:underline">Delete</button>
                </form>
            @endcan
        </p>
    </div>

    <div x-show="editing" x-cloak>
        <form method="POST" action="{{ $updateUrl }}" enctype="multipart/form-data" class="space-y-2">
            @csrf
            @method('PUT')
            <textarea name="comment" rows="2" class="block w-full rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800" required>{{ $comment->comment }}</textarea>

            @can('manageAttachments', $comment)
                @if ($comment->getMedia('attachments')->isNotEmpty())
                    <div class="flex flex-wrap gap-3">
                        @foreach ($comment->getMedia('attachments') as $attachment)
                            <label class="inline-flex items-center gap-1 text-xs text-gray-500">
                                <input type="checkbox" name="remove_media_ids[]" value="{{ $attachment->id }}">
                                Remove "{{ $attachment->file_name }}"
                            </label>
                        @endforeach
                    </div>
                @endif
                <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.mp4,.mov,.avi" class="block w-full text-xs">
            @endcan

            @error('files')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
            @error('comment')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror

            <div class="flex gap-2">
                <button type="submit" class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Save</button>
                <button type="button" x-on:click="editing = false" class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
            </div>
        </form>
    </div>
</div>
