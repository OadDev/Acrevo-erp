<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$audit->title" />
    </x-slot>

    <x-card class="max-w-2xl">
        <dl class="space-y-3 text-sm">
            <div><dt class="text-gray-400">Type</dt><dd><x-badge color="indigo" :status="$audit->type" /></dd></div>
            <div><dt class="text-gray-400">Work Order</dt><dd>{{ $audit->workOrder?->work_order_no ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Auditor</dt><dd>{{ $audit->auditor->name }}</dd></div>
            <div><dt class="text-gray-400">Date</dt><dd>{{ $audit->audit_date->format('d M Y') }}</dd></div>
            <div><dt class="text-gray-400">Findings</dt><dd class="whitespace-pre-line">{{ $audit->findings ?: '—' }}</dd></div>
        </dl>
    </x-card>
</x-app-layout>
