@csrf

<div class="space-y-4">
    <div>
        <x-input-label for="type" value="Record Type" />
        <x-select-input id="type" name="type" class="mt-1 block w-full">
            @foreach (['gst' => 'GST', 'msme' => 'MSME', 'insurance' => 'Insurance', 'license' => 'License', 'patent' => 'Patent'] as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $record->type ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </x-select-input>
    </div>
    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name', $record->name ?? '') }}" required />
    </div>
    <div>
        <x-input-label for="number" value="Number" />
        <x-text-input id="number" name="number" class="mt-1 block w-full" value="{{ old('number', $record->number ?? '') }}" />
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="issued_date" value="Issued Date" />
            <x-text-input id="issued_date" type="date" name="issued_date" class="mt-1 block w-full" value="{{ old('issued_date', optional($record->issued_date ?? null)->format('Y-m-d')) }}" />
        </div>
        <div>
            <x-input-label for="expiry_date" value="Expiry Date" />
            <x-text-input id="expiry_date" type="date" name="expiry_date" class="mt-1 block w-full" value="{{ old('expiry_date', optional($record->expiry_date ?? null)->format('Y-m-d')) }}" />
        </div>
    </div>
    <div>
        <x-input-label for="notes" value="Notes" />
        <x-textarea-input id="notes" name="notes" rows="3" class="mt-1 block w-full">{{ old('notes', $record->notes ?? '') }}</x-textarea-input>
    </div>
    <div>
        <x-input-label value="{{ isset($record) ? 'Add More Files' : 'Files (one or more)' }}" />
        <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="mt-1 block w-full text-sm">
        @error('files')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    @if (isset($record) && $record->media->isNotEmpty())
        <div>
            <x-input-label value="Uploaded Files" />
            <div class="mt-1 divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                @foreach ($record->media as $file)
                    <div class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                        <a href="{{ $file->getUrl() }}" target="_blank" class="flex flex-1 items-center gap-2 truncate">
                            <x-icon name="file-text" class="h-4 w-4 shrink-0 text-gray-400" />
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $file->file_name }}</span>
                        </a>
                        <form method="POST" action="{{ route('company-records.media.destroy', [$record, $file]) }}" onsubmit="return confirm('Remove this file?')">
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
    <x-link-button :href="route('company-records.index')" variant="secondary">Cancel</x-link-button>
    <x-primary-button>Save Record</x-primary-button>
</div>
