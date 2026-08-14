<x-app-layout>
    <x-slot name="header">
        <x-page-header title="QC Inspection" :subtitle="$qcInspection->workOrder->work_order_no" />
    </x-slot>

    <x-card class="max-w-2xl">
        <dl class="space-y-3 text-sm">
            <div><dt class="text-gray-400">Type</dt><dd>{{ Str::title($qcInspection->inspection_type) }}</dd></div>
            <div><dt class="text-gray-400">Status</dt><dd><x-badge :status="$qcInspection->status" /></dd></div>
            <div><dt class="text-gray-400">Inspector</dt><dd>{{ $qcInspection->inspectedBy?->name ?? 'Unknown' }}</dd></div>
            <div><dt class="text-gray-400">Date</dt><dd>{{ $qcInspection->inspection_date->format('d M Y') }}</dd></div>
            <div><dt class="text-gray-400">Remarks</dt><dd>{{ $qcInspection->remarks ?: '—' }}</dd></div>
        </dl>
        <div class="mt-4">
            <x-link-button :href="route('work-orders.show', $qcInspection->workOrder)" variant="secondary">View Work Order</x-link-button>
        </div>
    </x-card>
</x-app-layout>
