<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-3 lg:col-span-2">
        @forelse ($workOrder->qcInspections as $inspection)
            <x-card>
                <div class="flex items-center justify-between">
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ Str::title($inspection->inspection_type) }} QC — {{ $inspection->inspection_date->format('d M Y') }}</p>
                    <x-badge :status="$inspection->status" />
                </div>
                <p class="mt-1 text-sm text-gray-500">{{ $inspection->remarks }}</p>
                <p class="mt-1 text-xs text-gray-400">By {{ $inspection->inspectedBy?->name ?? 'Unknown' }}</p>
            </x-card>
        @empty
            <x-empty-state icon="shield-check" title="No QC inspections yet" />
        @endforelse
    </div>

    @can('qc.perform')
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Record QC Inspection</h3>
            <x-link-button :href="route('qc.create', ['work_order_id' => $workOrder->id])" class="w-full justify-center">Perform QC Inspection</x-link-button>
        </x-card>
    @endcan
</div>
