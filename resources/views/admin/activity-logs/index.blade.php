<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Activity Logs" subtitle="Every change to critical records, across every department. This log cannot be edited or deleted.">
            <x-slot name="actions">
                <x-link-button :href="route('admin.activity-logs.pdf', request()->query())" variant="secondary">Download PDF</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('admin.activity-logs.index') }}" class="space-y-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by user or action description..." class="w-full rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">

            <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                <x-select-input name="log_name" class="text-sm">
                    <option value="">All Modules</option>
                    @foreach ($logNames as $logName)
                        <option value="{{ $logName }}" @selected(request('log_name') === $logName)>{{ ucwords(str_replace(['_', '-'], ' ', $logName)) }}</option>
                    @endforeach
                </x-select-input>
                <x-text-input type="date" name="from" class="text-sm" value="{{ request('from') }}" placeholder="From" />
                <x-text-input type="date" name="to" class="text-sm" value="{{ request('to') }}" placeholder="To" />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <x-primary-button class="justify-center">Apply Filters</x-primary-button>
                @if (request()->hasAny(['q', 'log_name', 'subject_type', 'from', 'to']))
                    <x-link-button :href="route('admin.activity-logs.index')" variant="secondary">Clear</x-link-button>
                @endif
            </div>
        </form>
    </x-card>

    <x-card :padded="false">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-5 py-3">User</th>
                        <th class="px-5 py-3">Role</th>
                        <th class="px-5 py-3">Action</th>
                        <th class="px-5 py-3">Subject</th>
                        <th class="px-5 py-3">Previous &rarr; New Value</th>
                        <th class="px-5 py-3">WO / Site</th>
                        <th class="px-5 py-3">Date / Time</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($activities as $activity)
                        @php
                            $old = collect($activity->properties->get('old', []));
                            $attributes = collect($activity->properties->get('attributes', []));
                            $role = $activity->properties->get('role');
                            $workOrderId = $activity->properties->get('work_order_id');
                        @endphp
                        <tr>
                            <td class="px-5 py-3">{{ $activity->causer?->name ?? 'System' }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ $role ?: '—' }}</td>
                            <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $activity->description }}</td>
                            <td class="px-5 py-3 text-gray-500">{{ class_basename($activity->subject_type ?? '') }} #{{ $activity->subject_id }}</td>
                            <td class="px-5 py-3 text-gray-500">
                                @if ($attributes->isEmpty())
                                    —
                                @else
                                    @foreach ($attributes as $field => $value)
                                        @php
                                            $oldValue = $old->get($field);
                                            $display = fn ($v) => is_array($v) ? json_encode($v) : $v;
                                        @endphp
                                        <div><span class="text-xs text-gray-400">{{ $field }}:</span> {{ $oldValue !== null ? $display($oldValue) : '—' }} &rarr; {{ $display($value) }}</div>
                                    @endforeach
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-500">
                                @if ($workOrderId && ($workOrder = \App\Models\WorkOrder::find($workOrderId)))
                                    {{ $workOrder->work_order_no }}
                                @else
                                    —
                                @endif
                            </td>
                            <td class="px-5 py-3 text-gray-400">{{ $activity->created_at->format('d M Y, h:i A') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-6 text-center text-gray-400">No activity recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="mt-4">{{ $activities->links() }}</div>
</x-app-layout>
