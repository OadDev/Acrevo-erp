<x-app-layout>
    <x-slot name="header">
        <x-page-header title="QC Inspections" subtitle="Pending, passed, failed, and rework-required inspections.">
            <x-slot name="actions">
                <x-select-input name="status" class="text-sm" onchange="location.href='{{ route('qc.index') }}?status='+this.value">
                    <option value="">All</option>
                    @foreach (['passed', 'failed', 'rework_required'] as $status)
                        <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::title(str_replace('_',' ',$status)) }}</option>
                    @endforeach
                </x-select-input>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card :padded="false">
        @if ($inspections->isEmpty())
            <div class="p-6"><x-empty-state icon="shield-check" title="No QC inspections recorded" description="Perform an inspection from a work order's QC tab." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Work Order</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Inspector</th>
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($inspections as $inspection)
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('work-orders.show', $inspection->workOrder) }}'">
                                <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $inspection->workOrder->work_order_no }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ Str::title($inspection->inspection_type) }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $inspection->inspectedBy?->name ?? 'Unknown' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $inspection->inspection_date->format('d M Y') }}</td>
                                <td class="px-5 py-3"><x-badge :status="$inspection->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $inspections->links() }}</div>
</x-app-layout>
