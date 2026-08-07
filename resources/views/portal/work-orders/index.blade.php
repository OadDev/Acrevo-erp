<x-app-layout>
    <x-slot name="header">
        <x-page-header title="My Projects" subtitle="Track progress on your active and completed work orders." />
    </x-slot>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($workOrders as $workOrder)
            <a href="{{ route('portal.work-orders.show', $workOrder) }}">
                <x-card class="h-full transition hover:border-indigo-300">
                    <div class="flex items-center justify-between">
                        <p class="font-medium text-gray-900 dark:text-white">{{ $workOrder->title }}</p>
                        <x-badge :status="$workOrder->status" />
                    </div>
                    <p class="mt-1 text-xs text-gray-400">{{ $workOrder->work_order_no }}</p>
                    <p class="mt-3 text-sm text-gray-500">Deadline: {{ optional($workOrder->deadline)->format('d M Y') ?? '—' }}</p>
                </x-card>
            </a>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <x-empty-state icon="briefcase" title="No projects yet" />
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $workOrders->links() }}</div>
</x-app-layout>
