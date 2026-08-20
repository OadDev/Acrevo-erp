<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Audit" :subtitle="$audit->title" />
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <form method="POST" action="{{ route('audits.update', $audit) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')
                <div class="space-y-4">
                    <div>
                        <x-input-label for="title" value="Title" />
                        <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ old('title', $audit->title) }}" required />
                    </div>
                    <div>
                        <x-input-label for="type" value="Audit Type" />
                        <x-select-input id="type" name="type" class="mt-1 block w-full">
                            @foreach (['internal' => 'Internal', 'financial' => 'Financial', 'project' => 'Project'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('type', $audit->type) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <div>
                        <x-input-label for="work_order_id" value="Related Work Order (optional)" />
                        <x-select-input id="work_order_id" name="work_order_id" class="mt-1 block w-full">
                            <option value="">—</option>
                            @foreach ($workOrders as $wo)
                                <option value="{{ $wo->id }}" @selected(old('work_order_id', $audit->work_order_id) == $wo->id)>{{ $wo->work_order_no }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <div>
                        <x-input-label for="status" value="Status" />
                        <x-select-input id="status" name="status" class="mt-1 block w-full">
                            @foreach (['scheduled' => 'Scheduled', 'in_progress' => 'In Progress', 'completed' => 'Completed'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('status', $audit->status) === $value)>{{ $label }}</option>
                            @endforeach
                        </x-select-input>
                    </div>
                    <div>
                        <x-input-label for="audit_date" value="Audit Date" />
                        <x-text-input id="audit_date" type="date" name="audit_date" class="mt-1 block w-full" value="{{ old('audit_date', optional($audit->audit_date)->format('Y-m-d')) }}" required />
                    </div>
                    <div>
                        <x-input-label for="findings" value="Findings" />
                        <x-textarea-input id="findings" name="findings" rows="4" class="mt-1 block w-full">{{ old('findings', $audit->findings) }}</x-textarea-input>
                    </div>
                    <div>
                        <x-input-label value="Add More Files" />
                        <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="mt-1 block w-full text-sm">
                        @error('files')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                    </div>
                </div>
                <div class="mt-6 flex justify-end gap-2">
                    <x-link-button :href="route('audits.index')" variant="secondary">Cancel</x-link-button>
                    <x-primary-button>Save Changes</x-primary-button>
                </div>
            </form>
        </x-card>

        <x-card :padded="false">
            <h3 class="p-4 pb-2 text-sm font-semibold text-gray-500">Uploaded Files</h3>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($audit->media as $file)
                    <div class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm">
                        <a href="{{ $file->getUrl() }}" target="_blank" class="flex flex-1 items-center gap-2 truncate">
                            <x-icon name="file-text" class="h-4 w-4 shrink-0 text-gray-400" />
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $file->file_name }}</span>
                        </a>
                        <form method="POST" action="{{ route('audits.media.destroy', [$audit, $file]) }}" onsubmit="return confirm('Remove this file?')">
                            @csrf
                            @method('DELETE')
                            <button class="shrink-0 text-xs font-medium text-rose-600 hover:underline">Remove</button>
                        </form>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-gray-400">No files uploaded yet.</p>
                @endforelse
            </div>
        </x-card>
    </div>
</x-app-layout>
