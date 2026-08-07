<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Reports" subtitle="Export operational data for offline analysis." />
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Work Orders by Status</h3>
            <div class="space-y-2">
                @foreach ($summary['work_orders_by_status'] as $status => $count)
                    <div class="flex items-center justify-between text-sm">
                        <x-badge :status="$status" />
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
            <x-link-button :href="route('reports.work-orders.export')" variant="secondary" class="mt-4">
                <x-icon name="download" class="h-4 w-4" /> Export Work Orders (Excel)
            </x-link-button>
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Tickets by Type</h3>
            <div class="space-y-2">
                @foreach ($summary['tickets_by_type'] as $type => $count)
                    <div class="flex items-center justify-between text-sm">
                        <x-badge color="indigo" :status="$type" />
                        <span class="font-medium text-gray-700 dark:text-gray-300">{{ $count }}</span>
                    </div>
                @endforeach
            </div>
            <x-link-button :href="route('reports.tickets.export')" variant="secondary" class="mt-4">
                <x-icon name="download" class="h-4 w-4" /> Export Tickets (Excel)
            </x-link-button>
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Payroll Export</h3>
            <form action="{{ route('reports.payroll.export') }}" method="GET" class="flex items-end gap-2">
                <div>
                    <x-input-label value="Month" />
                    <x-select-input name="month" class="mt-1 text-sm">
                        @foreach (range(1,12) as $m)<option value="{{ $m }}" @selected($m == now()->month)>{{ \Carbon\Carbon::create()->month($m)->format('M') }}</option>@endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="Year" />
                    <x-select-input name="year" class="mt-1 text-sm">
                        @foreach (range(now()->year - 2, now()->year) as $y)<option value="{{ $y }}" @selected($y == now()->year)>{{ $y }}</option>@endforeach
                    </x-select-input>
                </div>
                <x-primary-button><x-icon name="download" class="h-4 w-4" /> Export</x-primary-button>
            </form>
        </x-card>
    </div>
</x-app-layout>
