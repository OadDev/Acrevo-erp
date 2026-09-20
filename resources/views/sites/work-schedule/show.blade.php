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
            <form method="POST" action="{{ route('sites.work-schedule.store', $site) }}" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4" x-data="{ mode: '{{ $schedules->isEmpty() ? 'independent' : 'depends_on' }}' }">
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
                        <x-input-label value="Scheduling" />
                        <x-select-input name="schedule_mode" x-model="mode" class="mt-1 block w-full">
                            <option value="independent">Independent (its own start date)</option>
                            <option value="depends_on">Depends on another work</option>
                        </x-select-input>
                    </div>
                    <div x-show="mode === 'independent'">
                        <x-input-label for="start_date" value="Start Date" />
                        <x-text-input id="start_date" type="date" name="start_date" class="mt-1 block w-full" />
                    </div>
                    <div x-show="mode === 'depends_on'" class="sm:col-span-2 lg:col-span-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <x-input-label value="Depends On" />
                            <x-select-input name="depends_on_schedule_id" class="mt-1 block w-full">
                                <option value="">Select a work...</option>
                                @foreach ($schedules as $existing)
                                    <option value="{{ $existing->id }}">{{ $existing->work_name }}</option>
                                @endforeach
                            </x-select-input>
                        </div>
                        <div>
                            <x-input-label value="Gap After It Ends (days)" />
                            <x-text-input type="number" min="0" name="lag_days" class="mt-1 block w-full" value="0" />
                        </div>
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
                                <th class="px-4 py-3 text-right">Original Days</th>
                                <th class="px-4 py-3 text-right">Revised Days</th>
                                <th class="px-4 py-3">Original End</th>
                                <th class="px-4 py-3">Revised End</th>
                                <th class="px-4 py-3 text-right">Previous Work Delay</th>
                                <th class="px-4 py-3 text-right">Own Delay</th>
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
                            @php
                                [$dayStart, $dayEnd] = $schedule->dayRange();
                                $previousDelay = $schedule->previousWorkDelayDays();
                                $ownDelay = $schedule->ownDelayDays();
                            @endphp
                            <tr>
                                <td class="px-4 py-3 text-gray-500">{{ $loop->iteration }}</td>
                                <td class="px-4 py-3">
                                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $schedule->work_name }}</p>
                                    @if ($schedule->schedule_mode === 'depends_on' && $schedule->dependsOn)
                                        <p class="text-xs text-indigo-500">After: {{ $schedule->dependsOn->work_name }}</p>
                                    @else
                                        <p class="text-xs text-gray-400">Independent</p>
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
                                    <td class="px-4 py-3 text-right text-gray-500">{{ $schedule->original_duration_days }}d</td>
                                    <td class="px-4 py-3 text-right text-gray-500">{{ $schedule->revised_duration_days }}d</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $schedule->original_end_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-gray-500">{{ $schedule->revised_end_date->format('d M Y') }}</td>
                                    <td class="px-4 py-3 text-right font-medium {{ $previousDelay > 0 ? 'text-amber-600 dark:text-amber-400' : ($previousDelay < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400') }}">
                                        {{ $previousDelay > 0 ? '+'.$previousDelay : $previousDelay }}d
                                    </td>
                                    <td class="px-4 py-3 text-right font-medium {{ $ownDelay > 0 ? 'text-rose-600 dark:text-rose-400' : ($ownDelay < 0 ? 'text-emerald-600 dark:text-emerald-400' : 'text-gray-400') }}">
                                        {{ $ownDelay > 0 ? '+'.$ownDelay : $ownDelay }}d
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
            @php $priorSchedules = $schedules->filter(fn ($s) => $s->id < $schedule->id); @endphp
            <x-modal :name="'edit-schedule-'.$schedule->id" max-width="lg">
                <form method="POST" action="{{ route('sites.work-schedule.update', [$site, $schedule]) }}" class="p-6" x-data="{ mode: '{{ $schedule->schedule_mode }}' }">
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
                        <div>
                            <x-input-label value="Revised End Date" />
                            <x-text-input type="date" name="revised_end_date" class="mt-1 block w-full" />
                            <p class="mt-1 text-xs text-gray-400">Currently {{ $schedule->revised_end_date->format('d M Y') }}. Set a date here to override Duration above.</p>
                        </div>
                        @if ($priorSchedules->isEmpty())
                            <div>
                                <x-input-label value="Start Date" />
                                <x-text-input type="date" name="start_date" class="mt-1 block w-full" value="{{ $schedule->revised_start_date->format('Y-m-d') }}" />
                            </div>
                            <input type="hidden" name="schedule_mode" value="independent">
                        @else
                            <div>
                                <x-input-label value="Scheduling" />
                                <x-select-input name="schedule_mode" x-model="mode" class="mt-1 block w-full">
                                    <option value="independent">Independent (its own start date)</option>
                                    <option value="depends_on">Depends on another work</option>
                                </x-select-input>
                            </div>
                            <div x-show="mode === 'independent'">
                                <x-input-label value="Start Date" />
                                <x-text-input type="date" name="start_date" class="mt-1 block w-full" value="{{ $schedule->revised_start_date->format('Y-m-d') }}" />
                            </div>
                            <div x-show="mode === 'depends_on'">
                                <x-input-label value="Depends On" />
                                <x-select-input name="depends_on_schedule_id" class="mt-1 block w-full">
                                    <option value="">Select a work...</option>
                                    @foreach ($priorSchedules as $option)
                                        <option value="{{ $option->id }}" @selected($schedule->depends_on_schedule_id === $option->id)>{{ $option->work_name }}</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                            <div x-show="mode === 'depends_on'">
                                <x-input-label value="Gap After It Ends (days)" />
                                <x-text-input type="number" min="0" name="lag_days" class="mt-1 block w-full" value="{{ $schedule->lag_days }}" />
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

                    <div class="mt-6 rounded-lg border border-amber-200 bg-amber-50 p-4 dark:border-amber-500/30 dark:bg-amber-500/10">
                        <p class="text-sm font-semibold text-amber-800 dark:text-amber-400">Correct Original Baseline</p>
                        <p class="mt-1 text-xs text-amber-700 dark:text-amber-400/80">
                            Original Start / End Date ({{ $schedule->original_start_date->format('d M Y') }} – {{ $schedule->original_end_date->format('d M Y') }}) is normally frozen once set, so Variance stays meaningful. Only fill these in to fix a mistake made when this work was first added — it resets the baseline, not just the plan.
                        </p>
                        <div class="mt-3 grid grid-cols-1 gap-4 sm:grid-cols-2">
                            <div>
                                <x-input-label value="Original Start Date" />
                                <x-text-input type="date" name="original_start_date" class="mt-1 block w-full" />
                            </div>
                            <div>
                                <x-input-label value="Original Duration (days)" />
                                <x-text-input type="number" min="1" name="original_duration_days" class="mt-1 block w-full" />
                            </div>
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
