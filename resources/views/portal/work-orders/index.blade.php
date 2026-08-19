<x-app-layout>
    <x-slot name="header">
        <x-page-header title="My Projects" subtitle="Track progress on your active and completed work orders." />
    </x-slot>

    @if ($pendingInvoices->isNotEmpty())
        <x-card class="mb-6 border-amber-200 bg-amber-50 dark:border-amber-500/30 dark:bg-amber-500/10">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-semibold text-amber-700 dark:text-amber-300">Payment Requested</h3>
                    <p class="mt-1 text-sm text-amber-700/80 dark:text-amber-300/80">
                        You have {{ $pendingInvoices->count() }} {{ Str::plural('invoice', $pendingInvoices->count()) }} awaiting payment, totalling ₹{{ number_format($pendingInvoices->sum(fn ($i) => $i->balanceDue()), 2) }}.
                    </p>
                </div>
                <x-link-button :href="route('portal.invoices.index')" class="shrink-0">View Invoices</x-link-button>
            </div>
        </x-card>
    @endif

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
