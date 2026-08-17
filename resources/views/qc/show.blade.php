<x-app-layout>
    <x-slot name="header">
        <x-page-header title="QC Inspection" :subtitle="$qcInspection->workOrder->work_order_no">
            @if (auth()->user()->hasRole('Admin'))
                <x-slot name="actions">
                    <a href="{{ route('qc.edit', $qcInspection) }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Edit</a>
                    <form method="POST" action="{{ route('qc.destroy', $qcInspection) }}" onsubmit="return confirm('Permanently remove this QC inspection?')">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Remove</button>
                    </form>
                </x-slot>
            @endif
        </x-page-header>
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
