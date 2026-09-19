<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Site Work Schedule Planner" subtitle="Overall schedule across every accessible site.">
            <x-slot name="actions">
                <a href="{{ route('work-schedules.overall.pdf', request()->query()) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    <x-icon name="download" class="h-4 w-4" /> Download PDF
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('work-schedules.overall') }}" class="flex flex-wrap items-center gap-2">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by Site ID or client name..." class="w-full max-w-sm rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">
            <x-primary-button class="justify-center">Search</x-primary-button>
            @if (request()->hasAny(['q']))
                <x-link-button :href="route('work-schedules.overall')" variant="secondary">Clear</x-link-button>
            @endif
        </form>
    </x-card>

    <x-card :padded="false">
        @if ($sites->isEmpty())
            <div class="p-6"><x-empty-state icon="calendar-check" title="No scheduled sites found" description="Add a work schedule from a site's Work Schedule tab to see it here." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">S.No</th>
                            <th class="px-4 py-3">Site</th>
                            <th class="px-4 py-3 text-right">Works</th>
                            <th class="px-4 py-3">Start (Day 1)</th>
                            <th class="px-4 py-3">Original Completion</th>
                            <th class="px-4 py-3">Revised Completion</th>
                            <th class="px-4 py-3 text-right">Total Duration</th>
                            <th class="px-4 py-3 text-right">Delayed</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($sites as $site)
                            @php
                                $start = $site->workSchedules->min('original_start_date');
                                $originalEnd = $site->workSchedules->max('original_end_date');
                                $revisedEnd = $site->workSchedules->max('revised_end_date');
                                $delayedCount = $site->workSchedules->filter->isDelayed()->count();
                            @endphp
                            <tr>
                                <td class="px-4 py-3 text-gray-500">{{ $sites->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $site->site_no }}</p>
                                    <p class="text-xs text-gray-400">{{ $site->client?->name ?? 'Removed client' }}</p>
                                </td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ $site->workSchedules->count() }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $start?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $originalEnd?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $revisedEnd?->format('d M Y') ?? '—' }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ $start && $revisedEnd ? $start->diffInDays($revisedEnd) + 1 : 0 }}d</td>
                                <td class="px-4 py-3 text-right {{ $delayedCount > 0 ? 'font-medium text-rose-600 dark:text-rose-400' : 'text-gray-400' }}">{{ $delayedCount }}</td>
                                <td class="px-4 py-3 text-right">
                                    <a href="{{ route('sites.work-schedule.show', $site) }}" class="text-xs font-medium text-indigo-600 hover:underline">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $sites->links() }}</div>
</x-app-layout>
