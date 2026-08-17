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
                    @if ($workOrder->status === 'client_review')
                        <form method="POST" action="{{ route('work-orders.complete', $workOrder) }}" onsubmit="return confirm('Mark this work order completed on the client\'s behalf?')">
                            @csrf
                            <x-primary-button>Mark Completed</x-primary-button>
                        </form>
                    @endif
                @endcan
                @if (auth()->user()->hasRole('Admin'))
                    <a href="{{ route('work-orders.edit', $workOrder) }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Edit</a>
                    <form method="POST" action="{{ route('work-orders.destroy', $workOrder) }}" onsubmit="return confirm('Permanently remove this work order? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Remove</button>
                    </form>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    @php
        $canSeeCompanyLedger = auth()->user()->hasAnyRole(['Admin', 'Finance']);
        $tabs = [
            'site' => 'Site',
            'overview' => 'Overview',
            'team' => 'Team',
            'checklist' => 'Daily Work with Checklist',
            'progress' => 'Progress & Media',
            'materials' => 'Material Inward and Daily Material Used Entry',
            'manpower' => 'Used Man Power Budget',
            'mb' => 'Measurement Book',
            'summary' => 'Monthly Summary',
            'ledger' => 'Site Ledger',
        ];
        if ($canSeeCompanyLedger) {
            $tabs['company-ledger'] = 'Company Ledger';
        }
        $tabs += [
            'qc' => 'QC',
            'approvals' => 'Approval Requests',
            'tickets' => 'Tickets',
        ];
        $validTabs = array_keys($tabs);
        $initialTab = in_array(request('tab'), $validTabs, true) ? request('tab') : 'site';
    @endphp
    <div x-data="{ tab: '{{ $initialTab }}' }">
        <div class="mb-6 flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-800">
            @foreach ($tabs as $key => $label)
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
        <div x-show="tab === 'manpower'" x-cloak>
            @include('work-orders.tabs.manpower')
        </div>
        <div x-show="tab === 'mb'" x-cloak>
            @include('work-orders.tabs.measurement-ledger')
        </div>
        <div x-show="tab === 'summary'" x-cloak>
            @include('work-orders.tabs.summary')
        </div>
        <div x-show="tab === 'ledger'" x-cloak>
            @include('work-orders.tabs.ledger')
        </div>
        @if ($canSeeCompanyLedger)
            <div x-show="tab === 'company-ledger'" x-cloak>
                @include('work-orders.tabs.company-ledger')
            </div>
        @endif
        <div x-show="tab === 'qc'" x-cloak>
            @include('work-orders.tabs.qc')
        </div>
        <div x-show="tab === 'approvals'" x-cloak>
            @include('work-orders.tabs.approvals')
        </div>
        <div x-show="tab === 'tickets'" x-cloak>
            @include('work-orders.tabs.tickets')
        </div>
    </div>
</x-app-layout>
