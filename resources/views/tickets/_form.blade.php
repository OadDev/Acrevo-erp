@csrf
<input type="hidden" name="work_order_id" value="{{ $workOrder->id }}">

<div class="mb-5 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
    {{ $workOrder->work_order_no }} — {{ $workOrder->title }} ({{ $workOrder->client?->name ?? 'Unknown client' }})
</div>

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <x-input-label for="title" value="Title" />
        <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ old('title', $ticket->title ?? '') }}" required />
    </div>

    <div>
        <x-input-label for="type" value="Type" />
        <x-select-input id="type" name="type" class="mt-1 block w-full">
            @foreach (['delay', 'material', 'client_change', 'quality', 'safety', 'technical', 'finance', 'internal'] as $type)
                <option value="{{ $type }}" @selected(old('type', $ticket->type ?? '') === $type)>{{ Str::title(str_replace('_',' ',$type)) }}</option>
            @endforeach
        </x-select-input>
    </div>

    <div>
        <x-input-label for="priority" value="Priority" />
        <x-select-input id="priority" name="priority" class="mt-1 block w-full">
            @foreach (['low', 'medium', 'high', 'critical'] as $priority)
                <option value="{{ $priority }}" @selected(old('priority', $ticket->priority ?? 'medium') === $priority)>{{ ucfirst($priority) }}</option>
            @endforeach
        </x-select-input>
    </div>

    <div>
        <x-input-label for="department_id" value="Department" />
        <x-select-input id="department_id" name="department_id" class="mt-1 block w-full">
            <option value="">—</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(old('department_id', $ticket->department_id ?? '') == $department->id)>{{ $department->name }}</option>
            @endforeach
        </x-select-input>
    </div>

    <div>
        <x-input-label for="assigned_to" value="Assign To" />
        <x-select-input id="assigned_to" name="assigned_to" class="mt-1 block w-full">
            <option value="">Unassigned</option>
            @foreach ($assignees as $user)
                <option value="{{ $user->id }}" @selected(old('assigned_to', $ticket->assigned_to ?? '') == $user->id)>{{ $user->name }}</option>
            @endforeach
        </x-select-input>
    </div>

    <div>
        <x-input-label for="due_date" value="Due Date" />
        <x-text-input id="due_date" type="date" name="due_date" class="mt-1 block w-full" value="{{ old('due_date', optional($ticket->due_date ?? null)->format('Y-m-d')) }}" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="description" value="Description" />
        <x-textarea-input id="description" name="description" rows="4" class="mt-1 block w-full">{{ old('description', $ticket->description ?? '') }}</x-textarea-input>
    </div>

    <div class="sm:col-span-2">
        <x-input-label value="{{ isset($ticket) ? 'Add More Files' : 'Attachments (one or more)' }}" />
        <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="mt-1 block w-full text-sm">
        @error('files')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    @if (isset($ticket) && $ticket->media->isNotEmpty())
        <div class="sm:col-span-2">
            <x-input-label value="Attached Files" />
            <div class="mt-1 divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                @foreach ($ticket->media as $file)
                    <div class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                        <a href="{{ $file->getUrl() }}" target="_blank" class="flex flex-1 items-center gap-2 truncate">
                            <x-icon name="file-text" class="h-4 w-4 shrink-0 text-gray-400" />
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $file->file_name }}</span>
                        </a>
                        <form method="POST" action="{{ route('tickets.media.destroy', [$ticket, $file]) }}" onsubmit="return confirm('Remove this file?')">
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
    <x-link-button :href="route('work-orders.show', $workOrder)" variant="secondary">Cancel</x-link-button>
    <x-primary-button>Save Ticket</x-primary-button>
</div>
