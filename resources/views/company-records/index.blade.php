<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Company Records" subtitle="GST, MSME, insurance, licenses, and patents.">
            <x-slot name="actions">
                @can('company_records.manage')
                    <x-link-button :href="route('company-records.create')"><x-icon name="plus" class="h-4 w-4" /> Add Record</x-link-button>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    @if ($records->isEmpty())
        <x-empty-state icon="building" title="No company records yet" />
    @else
        <div class="space-y-6">
            @foreach ($records as $type => $items)
                <x-card :padded="false">
                    <div class="border-b border-gray-100 px-5 py-3 dark:border-gray-800">
                        <h3 class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ strtoupper($type) }}</h3>
                    </div>
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($items as $record)
                                <tr>
                                    <td class="px-5 py-3">
                                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ $record->name }}</p>
                                        <p class="text-xs text-gray-400">{{ $record->number }}</p>
                                    </td>
                                    <td class="px-5 py-3 text-gray-500">Expires: {{ optional($record->expiry_date)->format('d M Y') ?? '—' }}</td>
                                    <td class="px-5 py-3 text-right">
                                        @can('company_records.manage')
                                            <a href="{{ route('company-records.edit', $record) }}" class="text-sm text-indigo-600 hover:underline">Edit</a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-card>
            @endforeach
        </div>
    @endif
</x-app-layout>
