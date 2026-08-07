<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Auditing" subtitle="Internal, financial, and project audits.">
            <x-slot name="actions">
                @can('audit.manage')
                    <x-link-button :href="route('audits.create')"><x-icon name="plus" class="h-4 w-4" /> New Audit</x-link-button>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card :padded="false">
        @if ($audits->isEmpty())
            <div class="p-6"><x-empty-state icon="search-check" title="No audits recorded" /></div>
        @else
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($audits as $audit)
                        <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('audits.show', $audit) }}'">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800 dark:text-gray-200">{{ $audit->title }}</p>
                                <p class="text-xs text-gray-400">{{ $audit->workOrder?->work_order_no }}</p>
                            </td>
                            <td class="px-4 py-3"><x-badge color="indigo" :status="$audit->type" /></td>
                            <td class="px-4 py-3 text-gray-500">{{ $audit->audit_date->format('d M Y') }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $audit->auditor->name }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    <div class="mt-4">{{ $audits->links() }}</div>
</x-app-layout>
