<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Task" :subtitle="$task->title" />
    </x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('tasks.update', $task) }}" enctype="multipart/form-data">
            @csrf
            @method('PUT')

            <div class="space-y-5">
                <div>
                    <x-input-label for="assigned_to" value="Assign To" />
                    <x-select-input id="assigned_to" name="assigned_to" class="mt-1 block w-full" required>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(old('assigned_to', $task->assigned_to) == $user->id)>{{ $user->name }} @if ($user->designation) ({{ $user->designation }}) @endif</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="title" value="Task Description" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ old('title', $task->title) }}" required />
                </div>

                <div>
                    <x-input-label for="description" value="Additional Details (optional)" />
                    <x-textarea-input id="description" name="description" rows="3" class="mt-1 block w-full">{{ old('description', $task->description) }}</x-textarea-input>
                </div>

                <div>
                    <x-input-label for="due_date" value="Due Date" />
                    <x-text-input id="due_date" type="date" name="due_date" class="mt-1 block w-full" value="{{ old('due_date', $task->due_date->format('Y-m-d')) }}" required />
                </div>

                <div>
                    <x-input-label value="Add More Attachments (optional, multiple allowed)" />
                    <input type="file" name="attachments[]" multiple class="mt-1 block w-full text-sm">
                    @error('attachments')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                @if ($task->getMedia('attachments')->isNotEmpty())
                    <div>
                        <x-input-label value="Uploaded Attachments" />
                        <div class="mt-1 divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                            @foreach ($task->getMedia('attachments') as $file)
                                <div class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                                    <a href="{{ $file->getUrl() }}" target="_blank" class="flex flex-1 items-center gap-2 truncate">
                                        <x-icon name="file-text" class="h-4 w-4 shrink-0 text-gray-400" />
                                        <span class="truncate text-gray-700 dark:text-gray-300">{{ $file->file_name }}</span>
                                    </a>
                                    <form method="POST" action="{{ route('tasks.media.destroy', [$task, $file]) }}" onsubmit="return confirm('Remove this attachment?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="shrink-0 text-xs font-medium text-rose-600 hover:underline">Remove</button>
                                    </form>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-link-button :href="route('tasks.show', $task)" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
