<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Site Work Schedule Planner" subtitle="Every site, its scheduled work, dates, and assigned team - in one place.">
            <x-slot name="actions">
                <a href="{{ route('work-schedules.overall.pdf', request()->query()) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    <x-icon name="download" class="h-4 w-4" /> Download Summary PDF
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div
        x-data="{
            tab: 'list',
            works: @js($allWorks),
            search: '',
            teamFilter: '',
            selectedDate: '{{ now()->format('Y-m-d') }}',
            get teams() {
                return [...new Set(this.works.map(w => w.team).filter(Boolean))].sort();
            },
            get filteredWorks() {
                const s = this.search.toLowerCase();
                return this.works.filter(w => {
                    if (this.teamFilter && w.team !== this.teamFilter) return false;
                    if (!s) return true;
                    return w.site_no.toLowerCase().includes(s)
                        || w.client_name.toLowerCase().includes(s)
                        || w.work_name.toLowerCase().includes(s)
                        || (w.team && w.team.toLowerCase().includes(s));
                });
            },
            get worksOnSelectedDate() {
                return this.works
                    .filter(w => w.start <= this.selectedDate && this.selectedDate <= w.end)
                    .sort((a, b) => a.site_no.localeCompare(b.site_no));
            },
            shiftDate(days) {
                const d = new Date(this.selectedDate + 'T00:00:00');
                d.setDate(d.getDate() + days);
                this.selectedDate = d.toISOString().slice(0, 10);
            },
            statusLabel(status) {
                return status.replace('_', ' ').replace(/\b\w/g, c => c.toUpperCase());
            },
            statusClass(status, delayed) {
                if (delayed) return 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-400';
                if (status === 'completed') return 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400';
                if (status === 'in_progress') return 'bg-blue-50 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400';
                return 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300';
            },
        }"
    >
        <div class="mb-4 flex gap-1 border-b border-gray-200 dark:border-gray-800">
            <button type="button" @click="tab = 'list'" :class="tab === 'list' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="border-b-2 px-4 py-2.5 text-sm font-semibold">All Works</button>
            <button type="button" @click="tab = 'calendar'" :class="tab === 'calendar' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="border-b-2 px-4 py-2.5 text-sm font-semibold">Calendar</button>
            <button type="button" @click="tab = 'summary'" :class="tab === 'summary' ? 'border-indigo-600 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400'" class="border-b-2 px-4 py-2.5 text-sm font-semibold">Per-Site Summary</button>
        </div>

        {{-- All Works: every work item across every accessible site, combined --}}
        <div x-show="tab === 'list'" x-cloak>
            <x-card class="mb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <input type="search" x-model="search" placeholder="Search site, client, work, or team..." class="w-full max-w-sm rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">
                    <select x-model="teamFilter" class="rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">
                        <option value="">All teams</option>
                        <template x-for="team in teams" :key="team">
                            <option :value="team" x-text="team"></option>
                        </template>
                    </select>
                    <span class="text-xs text-gray-400" x-text="filteredWorks.length + ' of ' + works.length + ' works'"></span>
                </div>
            </x-card>

            <x-card :padded="false">
                <div x-show="works.length === 0" class="p-6">
                    <x-empty-state icon="calendar-check" title="No scheduled sites found" description="Add a work schedule from a site's Work Schedule tab to see it here." />
                </div>
                <div x-show="works.length > 0" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3">Site</th>
                                <th class="px-4 py-3">Work</th>
                                <th class="px-4 py-3">Team</th>
                                <th class="px-4 py-3">Start</th>
                                <th class="px-4 py-3">End</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="work in filteredWorks" :key="work.site_id + work.work_name + work.start">
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-800 dark:text-gray-200" x-text="work.site_no"></p>
                                        <p class="text-xs text-gray-400" x-text="work.client_name"></p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300" x-text="work.work_name"></td>
                                    <td class="px-4 py-3 text-gray-500" x-text="work.team ?? '—'"></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500" x-text="work.start"></td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500" x-text="work.end"></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass(work.status, work.is_delayed)" x-text="work.is_delayed ? 'Delayed' : statusLabel(work.status)"></span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a :href="work.show_url" class="text-xs font-medium text-indigo-600 hover:underline">Open Site</a>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="filteredWorks.length === 0">
                                <td colspan="7" class="px-4 py-6 text-center text-sm text-gray-400">No works match this search.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        {{-- Calendar: pick a date, see which sites have work that day and what's planned --}}
        <div x-show="tab === 'calendar'" x-cloak>
            <x-card class="mb-4">
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" @click="shiftDate(-1)" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                        <x-icon name="arrow-left" class="h-4 w-4" />
                    </button>
                    <input type="date" x-model="selectedDate" class="rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">
                    <button type="button" @click="shiftDate(1)" class="rounded-lg border border-gray-200 p-2 text-gray-500 hover:bg-gray-50 dark:border-gray-700 dark:hover:bg-gray-800">
                        <x-icon name="arrow-left" class="h-4 w-4 rotate-180" />
                    </button>
                    <button type="button" @click="selectedDate = new Date().toISOString().slice(0, 10)" class="rounded-lg border border-gray-200 px-3 py-2 text-xs font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Today</button>
                    <span class="text-xs text-gray-400" x-text="worksOnSelectedDate.length + ' site(s) with work scheduled on this date'"></span>
                </div>
            </x-card>

            <x-card :padded="false">
                <div x-show="worksOnSelectedDate.length === 0" class="p-6">
                    <x-empty-state icon="calendar-check" title="Nothing scheduled" description="No site has work planned on the selected date." />
                </div>
                <div x-show="worksOnSelectedDate.length > 0" class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead class="bg-gray-50 dark:bg-gray-800/50">
                            <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                <th class="px-4 py-3">Site</th>
                                <th class="px-4 py-3">Work Planned</th>
                                <th class="px-4 py-3">Team</th>
                                <th class="px-4 py-3">Status</th>
                                <th class="px-4 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            <template x-for="work in worksOnSelectedDate" :key="work.site_id + work.work_name + work.start">
                                <tr>
                                    <td class="px-4 py-3">
                                        <p class="font-medium text-gray-800 dark:text-gray-200" x-text="work.site_no"></p>
                                        <p class="text-xs text-gray-400" x-text="work.client_name"></p>
                                    </td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300" x-text="work.work_name"></td>
                                    <td class="px-4 py-3 text-gray-500" x-text="work.team ?? 'Unassigned'"></td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium" :class="statusClass(work.status, work.is_delayed)" x-text="work.is_delayed ? 'Delayed' : statusLabel(work.status)"></span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <a :href="work.show_url" class="text-xs font-medium text-indigo-600 hover:underline">Open Site</a>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </x-card>
        </div>

        {{-- Per-Site Summary: one row per site, with day-1/completion/duration/delay counts --}}
        <div x-show="tab === 'summary'" x-cloak>
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
        </div>
    </div>
</x-app-layout>
