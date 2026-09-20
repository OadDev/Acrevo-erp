@php
    $tabs = \App\Support\WorkOrderPdfSections::forUser(auth()->user());
    $canSeeCompanyLedger = array_key_exists('company-ledger', $tabs);
    $canSeeLedger = array_key_exists('ledger', $tabs);
    $validTabs = array_keys($tabs);
    $initialTab = in_array(request('tab'), $validTabs, true) ? request('tab') : 'site';
@endphp
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

                <x-dropdown align="right" width="64">
                    <x-slot name="trigger">
                        <button type="button" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                            <x-icon name="download" class="h-4 w-4" /> Download PDF
                        </button>
                    </x-slot>
                    <x-slot name="content">
                        <x-dropdown-link href="{{ route('work-orders.pdf', $workOrder) }}" class="font-semibold">Full Work Order</x-dropdown-link>
                        <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                        @foreach ($tabs as $key => $label)
                            <x-dropdown-link href="{{ route('work-orders.pdf.section', [$workOrder, $key]) }}">{{ $label }}</x-dropdown-link>
                        @endforeach
                        <div class="my-1 border-t border-gray-100 dark:border-gray-700"></div>
                        <x-dropdown-link href="{{ route('work-orders.zip', $workOrder) }}" class="font-semibold">ZIP — Details + All Attachments</x-dropdown-link>
                    </x-slot>
                </x-dropdown>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div x-data="{ tab: '{{ $initialTab }}' }">
        <div class="mb-6 flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-800">
            @foreach ($tabs as $key => $label)
                <button
                    @click="tab = '{{ $key }}'"
                    :class="tab === '{{ $key }}' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                    class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition"
                >{{ $label }}</button>
            @endforeach
            <button
                @click="tab = 'discussion'"
                :class="tab === 'discussion' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium transition"
            >Discussion</button>
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
        @if ($canSeeLedger)
            <div x-show="tab === 'ledger'" x-cloak>
                @include('work-orders.tabs.ledger')
            </div>
        @endif
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
        <div x-show="tab === 'discussion'" x-cloak>
            <x-discussion-card :conversation="$discussion" title="Work Order Discussion" />
        </div>
    </div>
</x-app-layout>
