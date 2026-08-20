<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Legal Document" :subtitle="$document->title" />
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <form method="POST" action="{{ route('legal.update', $document) }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                @method('PUT')

                <div>
                    <x-input-label for="title" value="Title" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ old('title', $document->title) }}" required />
                </div>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <x-input-label for="type" value="Type" />
                        <x-select-input id="type" name="type" class="mt-1 block w-full">
                            @foreach (['agreement','contract','notice','other'] as $type)
                                <option value="{{ $type }}" @selected(old('type', $document->type) === $type)>{{ ucfirst($type) }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div>
                        <x-input-label for="status" value="Status" />
                        <x-select-input id="status" name="status" class="mt-1 block w-full">
                            @foreach (['active','expired','terminated'] as $status)
                                <option value="{{ $status }}" @selected(old('status', $document->status) === $status)>{{ ucfirst($status) }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div>
                        <x-input-label for="reference_no" value="Reference No." />
                        <x-text-input id="reference_no" name="reference_no" class="mt-1 block w-full" value="{{ old('reference_no', $document->reference_no) }}" />
                    </div>

                    <div></div>

                    <div>
                        <x-input-label for="issued_date" value="Issued Date" />
                        <x-text-input id="issued_date" type="date" name="issued_date" class="mt-1 block w-full" value="{{ old('issued_date', optional($document->issued_date)->format('Y-m-d')) }}" />
                    </div>

                    <div>
                        <x-input-label for="expiry_date" value="Expiry Date" />
                        <x-text-input id="expiry_date" type="date" name="expiry_date" class="mt-1 block w-full" value="{{ old('expiry_date', optional($document->expiry_date)->format('Y-m-d')) }}" />
                    </div>
                </div>

                <div>
                    <x-input-label for="notes" value="Notes" />
                    <x-textarea-input id="notes" name="notes" rows="3" class="mt-1 block w-full">{{ old('notes', $document->notes) }}</x-textarea-input>
                </div>

                <div>
                    <x-input-label value="Add More Files" />
                    <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="mt-1 block w-full text-sm">
                    @error('files')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-2">
                    <a href="{{ route('legal.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</a>
                    <x-primary-button>Save Changes</x-primary-button>
                </div>
            </form>
        </x-card>

        <x-card :padded="false">
            <h3 class="p-4 pb-2 text-sm font-semibold text-gray-500">Uploaded Files</h3>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($document->media as $file)
                    <div class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm">
                        <a href="{{ $file->getUrl() }}" target="_blank" class="flex flex-1 items-center gap-2 truncate">
                            <x-icon name="file-text" class="h-4 w-4 shrink-0 text-gray-400" />
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $file->file_name }}</span>
                        </a>
                        <form method="POST" action="{{ route('legal.media.destroy', [$document, $file]) }}" onsubmit="return confirm('Remove this file?')">
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
