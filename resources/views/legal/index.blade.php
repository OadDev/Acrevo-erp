<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Legal" subtitle="Agreements, contracts, and legal notices." />
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card :padded="false" class="lg:col-span-2">
            @if ($documents->isEmpty())
                <div class="p-6"><x-empty-state icon="scale" title="No legal documents yet" /></div>
            @else
                <div class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($documents as $document)
                        <div class="px-4 py-3">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $document->title }}</p>
                                    <p class="text-xs text-gray-400">{{ $document->reference_no }} · {{ $document->client?->name ?? $document->workOrder?->work_order_no }}</p>
                                </div>
                                <div class="flex shrink-0 items-center gap-2">
                                    <x-badge color="indigo" :status="$document->type" />
                                    <x-badge :status="$document->status" />
                                </div>
                            </div>

                            @if ($document->media->isNotEmpty())
                                <ul class="mt-2 space-y-1">
                                    @foreach ($document->media as $file)
                                        <li>
                                            <a href="{{ $file->getUrl() }}" target="_blank" class="inline-flex items-center gap-1.5 text-xs text-indigo-600 hover:underline">
                                                <x-icon name="file-text" class="h-3.5 w-3.5" /> {{ $file->file_name }}
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            <div class="mt-2 flex items-center gap-3 text-xs text-gray-400">
                                <span>Expiry: {{ optional($document->expiry_date)->format('d M Y') ?? '—' }}</span>
                                @can('legal.manage')
                                    <a href="{{ route('legal.edit', $document) }}" class="font-medium text-indigo-600 hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('legal.destroy', $document) }}" onsubmit="return confirm('Remove this legal document and all its files?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="font-medium text-rose-600 hover:underline">Delete</button>
                                    </form>
                                @endcan
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </x-card>

        @can('legal.manage')
            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">New Legal Document</h3>
                <form method="POST" action="{{ route('legal.store') }}" enctype="multipart/form-data" class="space-y-2">
                    @csrf
                    <x-text-input name="title" placeholder="Title" class="w-full text-sm" required />
                    <x-select-input name="type" class="w-full text-sm">
                        @foreach (['agreement','contract','notice','other'] as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach
                    </x-select-input>
                    <x-text-input name="reference_no" placeholder="Reference No." class="w-full text-sm" />
                    <x-text-input type="date" name="issued_date" class="w-full text-sm" />
                    <x-text-input type="date" name="expiry_date" class="w-full text-sm" />
                    <x-textarea-input name="notes" rows="2" class="w-full text-sm" placeholder="Notes"></x-textarea-input>
                    <div>
                        <x-input-label value="Files (one or more)" class="text-xs" />
                        <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="mt-1 block w-full text-xs">
                    </div>
                    @error('files')<p class="text-xs text-rose-600">{{ $message }}</p>@enderror
                    <x-primary-button class="w-full justify-center">Save</x-primary-button>
                </form>
            </x-card>
        @endcan
    </div>

    <div class="mt-4">{{ $documents->links() }}</div>
</x-app-layout>
