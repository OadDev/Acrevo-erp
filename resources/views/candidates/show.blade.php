<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$candidate->name" :subtitle="$candidate->position_applied ?: 'Candidate'">
            <x-slot name="actions">
                <x-badge :status="$candidate->status" class="text-sm" />
                @can('employees.edit')
                    @if ($candidate->status !== 'hired')
                        <x-link-button :href="route('candidates.edit', $candidate)" variant="secondary">Edit</x-link-button>
                    @endif
                @endcan
                @can('employees.delete')
                    @if ($candidate->status !== 'hired')
                        <form method="POST" action="{{ route('candidates.destroy', $candidate) }}" onsubmit="return confirm('Remove this candidate record?')">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Remove</button>
                        </form>
                    @endif
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Details</h3>
            <dl class="space-y-3 text-sm">
                <div><dt class="text-gray-400">Phone</dt><dd class="text-gray-800 dark:text-gray-200">{{ $candidate->phone ?: '—' }}</dd></div>
                <div><dt class="text-gray-400">Email</dt><dd class="text-gray-800 dark:text-gray-200">{{ $candidate->email ?: '—' }}</dd></div>
                <div><dt class="text-gray-400">Position Applied</dt><dd class="text-gray-800 dark:text-gray-200">{{ $candidate->position_applied ?: '—' }}</dd></div>
                <div><dt class="text-gray-400">Department</dt><dd class="text-gray-800 dark:text-gray-200">{{ $candidate->department?->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-400">Qualification</dt><dd class="text-gray-800 dark:text-gray-200">{{ $candidate->qualification ?: '—' }}</dd></div>
                <div><dt class="text-gray-400">Expected Salary</dt><dd class="text-gray-800 dark:text-gray-200">{{ $candidate->expected_salary ? '₹'.number_format($candidate->expected_salary, 2) : '—' }}</dd></div>
                <div><dt class="text-gray-400">Experience</dt><dd class="whitespace-pre-line text-gray-800 dark:text-gray-200">{{ $candidate->experience_summary ?: '—' }}</dd></div>
                <div><dt class="text-gray-400">Recorded By</dt><dd class="text-gray-800 dark:text-gray-200">{{ $candidate->createdBy?->name ?? '—' }}</dd></div>
                @if ($candidate->status === 'hired' && $candidate->employee)
                    <div><dt class="text-gray-400">Hired As</dt><dd><a href="{{ route('employees.show', $candidate->employee) }}" class="text-indigo-600 hover:underline">{{ $candidate->employee->name }}</a></dd></div>
                @endif
            </dl>
        </x-card>

        <x-card :padded="false">
            <h3 class="p-4 pb-2 text-sm font-semibold text-gray-500">Resume / Documents</h3>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($candidate->media as $file)
                    <div class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm">
                        <a href="{{ $file->getUrl() }}" target="_blank" class="flex flex-1 items-center gap-2 truncate">
                            <x-icon name="file-text" class="h-4 w-4 shrink-0 text-gray-400" />
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $file->file_name }}</span>
                        </a>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-gray-400">No documents uploaded yet.</p>
                @endforelse
            </div>
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Interview Outcome</h3>

            @if ($candidate->status === 'hired')
                <p class="text-sm text-gray-500">This candidate has been hired. See the linked worker record for further updates.</p>
            @else
                @can('employees.create')
                    <form method="POST" action="{{ route('candidates.decide', $candidate) }}" class="space-y-4">
                        @csrf
                        <div>
                            <x-input-label for="interview_date" value="Interview Date" />
                            <x-text-input id="interview_date" type="date" name="interview_date" class="mt-1 block w-full" value="{{ old('interview_date', optional($candidate->interview_date)->format('Y-m-d')) }}" />
                        </div>
                        <div>
                            <x-input-label for="interview_notes" value="Interview Notes" />
                            <x-textarea-input id="interview_notes" name="interview_notes" rows="3" class="mt-1 block w-full">{{ old('interview_notes', $candidate->interview_notes) }}</x-textarea-input>
                        </div>
                        <div>
                            <x-input-label for="status" value="Outcome" />
                            <x-select-input id="status" name="status" class="mt-1 block w-full">
                                <option value="pending" @selected($candidate->status === 'pending')>Pending</option>
                                <option value="selected" @selected($candidate->status === 'selected')>Selected</option>
                                <option value="not_selected" @selected($candidate->status === 'not_selected')>Not Selected</option>
                            </x-select-input>
                        </div>
                        <x-primary-button class="w-full justify-center">Save Outcome</x-primary-button>
                    </form>
                @endcan

                @if ($candidate->status === 'selected')
                    @can('employees.create')
                        <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                            <x-link-button :href="route('candidates.hire-form', $candidate)" class="w-full justify-center">Hire as Employee / Worker</x-link-button>
                        </div>
                    @endcan
                @endif
            @endif
        </x-card>
    </div>
</x-app-layout>
