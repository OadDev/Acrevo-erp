<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Work Schedule" :subtitle="$site->site_no.' — '.($site->client?->name ?? 'Removed client')">
            <x-slot name="actions">
                <a href="{{ route('sites.show', $site) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    <x-icon name="arrow-left" class="h-4 w-4" /> Back to Site
                </a>
                <a href="{{ route('sites.work-schedule.pdf', $site) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">
                    <x-icon name="download" class="h-4 w-4" /> Download PDF
                </a>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-card>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Project Start (Day 1)</p>
            <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $projectStart?->format('d M Y') ?? '—' }}</p>
        </x-card>
        <x-card>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Projected Completion</p>
            <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-gray-100">{{ $projectedCompletion?->format('d M Y') ?? '—' }}</p>
        </x-card>
        <x-card>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Total Duration</p>
            <p class="mt-1 text-lg font-semibold text-gray-800 dark:text-gray-100">
                {{ $projectStart && $projectedCompletion ? $projectStart->diffInDays($projectedCompletion) + 1 : 0 }} days
            </p>
        </x-card>
        <x-card>
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Delayed Works</p>
            <p class="mt-1 text-lg font-semibold {{ $schedules->filter->isDelayed()->count() > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-gray-800 dark:text-gray-100' }}">
                {{ $schedules->filter->isDelayed()->count() }} of {{ $schedules->count() }}
            </p>
        </x-card>
    </div>

    @can('work_schedules.manage')
        <x-card class="mb-6">
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Add Work</h3>
            <form method="POST" action="{{ route('sites.work-schedule.store', $site) }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <div class="sm:col-span-2 lg:col-span-2">
                    <x-input-label for="work_name" value="Work Name / Details" />
                    <x-text-input id="work_name" name="work_name" class="mt-1 block w-full" required placeholder="e.g. Plastering" />
                </div>
                <div>
                    <x-input-label for="duration_days" value="Duration (days)" />
                    <x-text-input id="duration_days" type="number" min="1" name="duration_days" class="mt-1 block w-full" required />
                </div>
                @if ($schedules->isEmpty())
                    <div>
                        <x-input-label for="start_date" value="Start Date (Day 1)" />
                        <x-text-input id="start_date" type="date" name="start_date" class="mt-1 block w-full" required />
                    </div>
                @else
                    <div>
                        <x-input-label for="lag_days" value="Gap Before Starting (days)" />
                        <x-text-input id="lag_days" type="number" min="0" name="lag_days" class="mt-1 block w-full" value="0" />
                    </div>
                    <div class="flex items-center gap-2 pt-6">
                        <input type="checkbox" id="is_parallel" name="is_parallel" value="1" class="rounded border-gray-300 text-indigo-600 dark:border-gray-600">
                        <x-input-label for="is_parallel" value="Runs parallel with previous work" class="!mb-0" />
                    </div>
                @endif
                <div class="sm:col-span-2 lg:col-span-4">
                    <x-input-label for="work_details" value="Notes (optional)" />
                    <textarea id="work_details" name="work_details" rows="2" class="mt-1 block w-full rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800"></textarea>
                </div>
                <div class="lg:col-span-4">
                    <x-primary-button type="submit">Add Work Schedule</x-primary-button>
                </div>
            </form>
        </x-card>
    @endcan

    <x-card :padded="false">
        @if ($schedules->isEmpty())
            <div class="p-6"><x-empty-state icon="calendar-check" title="No work schedules yet" description="Add the first work item above to start this site's schedule." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">S.No</th>
                            <th class="px-4 py-3">Work</th>
                            @can('work_schedules.view_dates')
                                <th class="px-4 py-3">Day</th>
                                <th class="px-4 py-3">Date</th>
                                <th class="px-4 py-3 text-right">Duration</th>
                                <th class="px-4 py-3">Original End</th>
                                <th class="px-4 py-3">Revised End</th>
                                <th class="px-4 py-3 text-right">Variance</th>
                            @endcan
                            @can('work_schedules.view_progress')
                                <th class="px-4 py-3">Progress</th>
                                <th class="px-4 py-3">Delay / Remarks</th>
                            @endcan
                            <th class="px-4 py-3">Status</th>
                            @can('work_schedules.manage')
                                <th class="px-4 py-3"></th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($schedules as $schedule)
                            @php [$dayStart, $dayEnd] = $schedule->dayRange(); $variance = $schedule->varianceDays(); @endphp
                            <tr>
                                <td class="px-4 py-3 text-gray-500">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $schedule->work_name }}</p>
                                    @if ($schedule->is_parallel)
                                        <p class="text-xs text-indigo-500">Parallel work</p>
                                    @endif
                                    @can('work_schedules.view_details')
                                        @if ($schedule->work_details)
                                            <p class="text-xs text-gray-400">{{ $schedule->work_details }}</p>
                                        @endif
                                    @endcan
                                </td>
                                @can('work_schedules.view_dates')
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500">Day {{ $dayStart }}{{ $dayEnd !== $dayStart ? '–'.$dayEnd : '' }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $schedule->revised_start_date->format('d/m') }}–{{ $schedule->revised_end_date->format('d/m/y') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-500">{{ $schedule->revised_duration_days }}d</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $schedule->original_end_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $schedule->revised_end_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-right font-medium {{ $variance > 0 ? 'text-rose-600 dark:text-rose-400' : ($variance < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400') }}">
                                        {{ $variance > 0 ? '+'.$variance : $variance }}d
                                    </td>
                                @endcan
                                @can('work_schedules.view_progress')
                                    <td class="px-4 py-3 text-gray-500">{{ $schedule->actual_progress_percent !== null ? $schedule->actual_progress_percent.'%' : '—' }}</td>
                                    <td class="px-4 py-3 text-gray-500">
                                        @if ($schedule->isDelayed())
                                            <span class="text-rose-600 dark:text-rose-400">Delayed</span>
                                        @endif
                                        @if ($schedule->delay_reason)
                                            <p class="text-xs text-gray-400">{{ $schedule->delay_reason }}</p>
                                        @endif
                                    </td>
                                @endcan
                                <td class="px-4 py-3"><x-badge :status="$schedule->status" /></td>
                                @can('work_schedules.manage')
                                    <td class="px-4 py-3 text-right whitespace-nowrap">
                                        <button type="button" x-data x-on:click="$dispatch('open-modal', 'edit-schedule-{{ $schedule->id }}')" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                                        <form method="POST" action="{{ route('sites.work-schedule.destroy', [$site, $schedule]) }}" class="inline" onsubmit="return confirm('Remove this work schedule item? Later works will be recalculated.')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Remove</button>
                                        </form>
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    @can('work_schedules.manage')
        @foreach ($schedules as $schedule)
            <x-modal :name="'edit-schedule-'.$schedule->id" max-width="lg">
                <form method="POST" action="{{ route('sites.work-schedule.update', [$site, $schedule]) }}" class="p-6">
                    @csrf
                    @method('PUT')
                    <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-100">Edit — {{ $schedule->work_name }}</h3>

                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div class="sm:col-span-2">
                            <x-input-label value="Work Name / Details" />
                            <x-text-input name="work_name" class="mt-1 block w-full" required value="{{ $schedule->work_name }}" />
                        </div>
                        <div>
                            <x-input-label value="Duration (days)" />
                            <x-text-input type="number" min="1" name="duration_days" class="mt-1 block w-full" required value="{{ $schedule->revised_duration_days }}" />
                        </div>
                        @if ($schedule->sequence_order === (int) $schedules->min('sequence_order'))
                            <div>
                                <x-input-label value="Start Date (Day 1)" />
                                <x-text-input type="date" name="start_date" class="mt-1 block w-full" value="{{ $schedule->revised_start_date->format('Y-m-d') }}" />
                            </div>
                        @else
                            <div>
                                <x-input-label value="Gap Before Starting (days)" />
                                <x-text-input type="number" min="0" name="lag_days" class="mt-1 block w-full" value="{{ $schedule->lag_days }}" />
                            </div>
                            <div class="flex items-center gap-2 pt-6">
                                <input type="checkbox" name="is_parallel" value="1" class="rounded border-gray-300 text-indigo-600 dark:border-gray-600" @checked($schedule->is_parallel)>
                                <x-input-label value="Runs parallel with previous work" class="!mb-0" />
                            </div>
                        @endif
                        <div>
                            <x-input-label value="Status" />
                            <x-select-input name="status" class="mt-1 block w-full">
                                @foreach (\App\Models\SiteWorkSchedule::STATUSES as $status)
                                    <option value="{{ $status }}" @selected($schedule->status === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="Actual Progress (%)" />
                            <x-text-input type="number" min="0" max="100" name="actual_progress_percent" class="mt-1 block w-full" value="{{ $schedule->actual_progress_percent }}" />
                        </div>
                        <div>
                            <x-input-label value="Actual Start Date" />
                            <x-text-input type="date" name="actual_start_date" class="mt-1 block w-full" value="{{ $schedule->actual_start_date?->format('Y-m-d') }}" />
                        </div>
                        <div>
                            <x-input-label value="Actual End Date" />
                            <x-text-input type="date" name="actual_end_date" class="mt-1 block w-full" value="{{ $schedule->actual_end_date?->format('Y-m-d') }}" />
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label value="Delay Reason / Remarks" />
                            <textarea name="delay_reason" rows="2" class="mt-1 block w-full rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">{{ $schedule->delay_reason }}</textarea>
                        </div>
                        <div class="sm:col-span-2">
                            <x-input-label value="Notes" />
                            <textarea name="work_details" rows="2" class="mt-1 block w-full rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">{{ $schedule->work_details }}</textarea>
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" x-data x-on:click="$dispatch('close-modal', 'edit-schedule-{{ $schedule->id }}')" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</button>
                        <x-primary-button type="submit">Save Changes</x-primary-button>
                    </div>
                </form>
            </x-modal>
        @endforeach
    @endcan
</x-app-layout>
