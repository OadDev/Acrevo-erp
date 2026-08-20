<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$employee->name" :subtitle="$employee->employee_code.' — '.$employee->designation">
            <x-slot name="actions">
                <x-badge :status="$employee->status" class="text-sm" />
                @can('employees.edit')
                    <x-link-button :href="route('employees.edit', $employee)" variant="secondary">Edit</x-link-button>
                @endcan
                @can('employees.delete')
                    @if ($employee->status === 'active')
                        <form method="POST" action="{{ route('employees.destroy', $employee) }}" onsubmit="return confirm('Mark this worker as relieved?')">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Mark Relieved</button>
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
                <div><dt class="text-gray-400">Phone</dt><dd class="text-gray-800 dark:text-gray-200">{{ $employee->phone ?: '—' }}</dd></div>
                <div><dt class="text-gray-400">Department</dt><dd class="text-gray-800 dark:text-gray-200">{{ $employee->department?->name ?? '—' }}</dd></div>
                <div><dt class="text-gray-400">Employment Type</dt><dd class="text-gray-800 dark:text-gray-200">{{ Str::title(str_replace('_',' ',$employee->employment_type)) }}</dd></div>
                <div><dt class="text-gray-400">Salary</dt><dd class="text-gray-800 dark:text-gray-200">₹{{ number_format($employee->salary_amount ?? 0, 2) }} / {{ $employee->salary_type }}</dd></div>
                <div><dt class="text-gray-400">Joining Date</dt><dd class="text-gray-800 dark:text-gray-200">{{ optional($employee->joining_date)->format('d M Y') ?? '—' }}</dd></div>
            </dl>
        </x-card>

        <x-card class="lg:col-span-2">
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Executive Team Memberships</h3>
            @forelse ($employee->executiveTeamMemberships as $membership)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                    @if ($membership->executiveTeam)
                        <a href="{{ route('executive-teams.show', $membership->executiveTeam) }}" class="text-indigo-600 hover:underline">{{ $membership->executiveTeam->name }}</a>
                    @else
                        <span class="text-gray-400">Removed team</span>
                    @endif
                    <span class="text-gray-400">{{ $membership->role_in_team }}</span>
                </div>
            @empty
                <p class="text-sm text-gray-400">Not assigned to any executive team.</p>
            @endforelse

            <h3 class="mb-4 mt-6 text-sm font-semibold text-gray-500">Recent Attendance</h3>
            <div class="flex flex-wrap gap-1">
                @forelse ($employee->attendances as $attendance)
                    <span title="{{ $attendance->date->format('d M Y') }}" class="h-6 w-6 rounded {{ $attendance->status === 'present' ? 'bg-emerald-400' : ($attendance->status === 'absent' ? 'bg-rose-400' : 'bg-amber-300') }}"></span>
                @empty
                    <p class="text-sm text-gray-400">No attendance records yet.</p>
                @endforelse
            </div>
        </x-card>
    </div>
</x-app-layout>
