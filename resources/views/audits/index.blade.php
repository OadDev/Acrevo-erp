<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Auditing" subtitle="Internal, financial, and project audits.">
            <x-slot name="actions">
                @can('audit.manage')
                    <x-link-button :href="route('audits.create')"><x-icon name="plus" class="h-4 w-4" /> New Audit</x-link-button>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('audits.index') }}" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            <x-select-input name="type" class="text-sm" onchange="this.form.submit()">
                <option value="">All Types</option>
                @foreach (['internal' => 'Internal', 'financial' => 'Financial', 'project' => 'Project'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('type') === $value)>{{ $label }}</option>
                @endforeach
            </x-select-input>
            <x-select-input name="status" class="text-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                @foreach (['scheduled' => 'Scheduled', 'in_progress' => 'In Progress', 'completed' => 'Completed'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </x-select-input>
            <x-select-input name="work_order_id" class="text-sm" onchange="this.form.submit()">
                <option value="">All Work Orders</option>
                @foreach ($workOrders as $wo)
                    <option value="{{ $wo->id }}" @selected(request('work_order_id') == $wo->id)>{{ $wo->work_order_no }}</option>
                @endforeach
            </x-select-input>
            <x-text-input type="date" name="from" class="text-sm" value="{{ request('from') }}" placeholder="From" />
            <x-text-input type="date" name="to" class="text-sm" value="{{ request('to') }}" placeholder="To" />
            <div class="flex gap-2">
                <x-primary-button class="justify-center">Filter</x-primary-button>
                @if (request()->hasAny(['type', 'status', 'work_order_id', 'from', 'to']))
                    <x-link-button :href="route('audits.index')" variant="secondary">Clear</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :padded="false">
        @if ($audits->isEmpty())
            <div class="p-6"><x-empty-state icon="search-check" title="No audits recorded" /></div>
        @else
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($audits as $audit)
                        <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <td class="cursor-pointer px-4 py-3" onclick="window.location='{{ route('audits.show', $audit) }}'">
                                <p class="font-medium text-gray-800 dark:text-gray-200">{{ $audit->title }}</p>
                                <p class="text-xs text-gray-400">
                                    {{ $audit->workOrder?->work_order_no }}
                                    @if ($audit->media->isNotEmpty())
                                        · {{ $audit->media->count() }} file(s)
                                    @endif
                                </p>
                            </td>
                            <td class="cursor-pointer px-4 py-3" onclick="window.location='{{ route('audits.show', $audit) }}'"><x-badge color="indigo" :status="$audit->type" /></td>
                            <td class="cursor-pointer px-4 py-3" onclick="window.location='{{ route('audits.show', $audit) }}'"><x-badge :status="$audit->status" /></td>
                            <td class="cursor-pointer px-4 py-3 text-gray-500" onclick="window.location='{{ route('audits.show', $audit) }}'">{{ $audit->audit_date->format('d M Y') }}</td>
                            <td class="cursor-pointer px-4 py-3 text-gray-500" onclick="window.location='{{ route('audits.show', $audit) }}'">{{ $audit->auditor->name }}</td>
                            <td class="px-4 py-3 text-right">
                                @can('audit.manage')
                                    <a href="{{ route('audits.edit', $audit) }}" class="text-sm text-indigo-600 hover:underline">Edit</a>
                                @endcan
                                @can('audit.delete')
                                    <form method="POST" action="{{ route('audits.destroy', $audit) }}" class="inline" onsubmit="return confirm('Remove this audit and its files?')">
                                        @csrf
                                        @method('DELETE')
                                        <button class="ml-3 text-sm text-rose-600 hover:underline">Delete</button>
                                    </form>
                                @endcan
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    <div class="mt-4">{{ $audits->links() }}</div>
</x-app-layout>
