@php
    $documentLabels = [
        'kyc' => 'Client KYC',
        'land_document' => 'Land Document',
        'gov_record' => 'Government Record',
        'other' => 'Other',
    ];
@endphp

<x-card :padded="false" class="mt-6">
    <h3 class="p-4 pb-0 text-sm font-semibold text-gray-500">Site Documents — KYC, Land & Government Records</h3>

    <div class="divide-y divide-gray-100 dark:divide-gray-800">
        @forelse ($site->media as $document)
            <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm">
                <a href="{{ $document->getUrl() }}" target="_blank" class="flex flex-1 items-center gap-2 truncate">
                    <x-icon name="file-text" class="h-4 w-4 shrink-0 text-gray-400" />
                    <span class="truncate text-gray-700 dark:text-gray-300">{{ $document->file_name }}</span>
                </a>
                <div class="flex shrink-0 items-center gap-3 text-xs text-gray-400">
                    <span>{{ $documentLabels[$document->collection_name] ?? Str::title(str_replace('_', ' ', $document->collection_name)) }}</span>
                    <span>{{ $document->created_at->timezone('Asia/Kolkata')->format('d M Y') }}</span>
                    @if (auth()->user()->hasRole('Admin'))
                        <form method="POST" action="{{ route('sites.documents.destroy', [$site, $document]) }}" onsubmit="return confirm('Remove this document?')">
                            @csrf
                            @method('DELETE')
                            <button class="font-medium text-rose-600 hover:underline">Delete</button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="px-4 py-6 text-center text-sm text-gray-400">No documents uploaded yet.</p>
        @endforelse
    </div>

    @can('work_orders.edit')
        <form method="POST" action="{{ route('sites.documents.store', $site) }}" enctype="multipart/form-data" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-4">
            @csrf
            <x-select-input name="category" class="text-sm">
                @foreach ($documentLabels as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </x-select-input>
            <input type="file" name="file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="col-span-2 text-sm sm:col-span-2" required>
            <x-primary-button class="justify-center">Upload</x-primary-button>
        </form>
    @endcan
</x-card>
