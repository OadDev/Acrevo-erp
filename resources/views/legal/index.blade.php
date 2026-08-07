<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Legal" subtitle="Agreements, contracts, and legal notices." />
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card :padded="false" class="lg:col-span-2">
            @if ($documents->isEmpty())
                <div class="p-6"><x-empty-state icon="scale" title="No legal documents yet" /></div>
            @else
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($documents as $document)
                            <tr>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $document->title }}</p>
                                    <p class="text-xs text-gray-400">{{ $document->reference_no }} · {{ $document->client?->name ?? $document->workOrder?->work_order_no }}</p>
                                </td>
                                <td class="px-4 py-3"><x-badge color="indigo" :status="$document->type" /></td>
                                <td class="px-4 py-3 text-gray-500">{{ optional($document->expiry_date)->format('d M Y') ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-card>

        @can('legal.manage')
            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">New Legal Document</h3>
                <form method="POST" action="{{ route('legal.store') }}" class="space-y-2">
                    @csrf
                    <x-text-input name="title" placeholder="Title" class="w-full text-sm" required />
                    <x-select-input name="type" class="w-full text-sm">
                        @foreach (['agreement','contract','notice','other'] as $type)<option value="{{ $type }}">{{ ucfirst($type) }}</option>@endforeach
                    </x-select-input>
                    <x-text-input name="reference_no" placeholder="Reference No." class="w-full text-sm" />
                    <x-text-input type="date" name="issued_date" class="w-full text-sm" />
                    <x-text-input type="date" name="expiry_date" class="w-full text-sm" />
                    <x-textarea-input name="notes" rows="2" class="w-full text-sm" placeholder="Notes"></x-textarea-input>
                    <x-primary-button class="w-full justify-center">Save</x-primary-button>
                </form>
            </x-card>
        @endcan
    </div>

    <div class="mt-4">{{ $documents->links() }}</div>
</x-app-layout>
