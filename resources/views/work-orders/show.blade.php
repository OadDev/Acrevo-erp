<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="($workOrder->site?->site_no ? $workOrder->site->site_no.' — ' : '').$workOrder->work_order_no" :subtitle="$workOrder->title">
            <x-slot name="actions">
                <x-badge :status="$workOrder->status" class="text-sm" />
                @can('work_orders.cancel')
                    @if (! in_array($workOrder->status, ['completed', 'cancelled']))
                        <form method="POST" action="{{ route('work-orders.cancel', $workOrder) }}" onsubmit="return confirm('Cancel this work order?')">
                            @csrf
                            <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Cancel</button>
                        </form>
                    @endif
                @endcan
                @can('work_orders.edit')
                    @if ($workOrder->status === 'final_qc')
                        <form method="POST" action="{{ route('work-orders.complete', $workOrder) }}">
                            @csrf
                            <x-primary-button>Mark Completed</x-primary-button>
                        </form>
                    @endif
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div x-data="{ tab: 'site' }">
        <div class="mb-6 flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-800">
            @foreach ([
                'site' => 'Site',
                'overview' => 'Overview',
                'team' => 'Team',
                'checklist' => 'Daily Checklist',
                'progress' => 'Progress & Media',
                'materials' => 'Materials & Labour',
                'mb' => 'Measurement Book & Ledger',
                'qc' => 'QC',
                'tickets' => 'Tickets',
            ] as $key => $label)
                <button
                    @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                    class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition"
                >{{ $label }}</button>
            @endforeach
        </div>

        <div x-show="tab === 'site'">
            @include('work-orders.tabs.site')
        </div>
        <div x-show="tab === 'overview'" x-cloak>
            @include('work-orders.tabs.overview')
        </div>
        <div x-show="tab === 'team'" x-cloak>
            @include('work-orders.tabs.team')
        </div>
        <div x-show="tab === 'checklist'" x-cloak>
            @include('work-orders.tabs.checklist')
        </div>
        <div x-show="tab === 'progress'" x-cloak>
            @include('work-orders.tabs.progress')
        </div>
        <div x-show="tab === 'materials'" x-cloak>
            @include('work-orders.tabs.materials')
        </div>
        <div x-show="tab === 'mb'" x-cloak>
            @include('work-orders.tabs.measurement-ledger')
        </div>
        <div x-show="tab === 'qc'" x-cloak>
            @include('work-orders.tabs.qc')
        </div>
        <div x-show="tab === 'tickets'" x-cloak>
            @include('work-orders.tabs.tickets')
        </div>
    </div>
</x-app-layout>
