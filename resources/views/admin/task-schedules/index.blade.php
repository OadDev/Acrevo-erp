<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Calendar Task Management" subtitle="Daily, weekly, monthly, and yearly recurring tasks per user or designation.">
            <x-slot name="actions">
                <x-link-button :href="route('admin.task-schedules.create', array_filter(['user_id' => $userId, 'role' => $role]))">+ New Calendar Task</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card class="mb-6">
        <h3 class="mb-3 text-sm font-semibold text-gray-500">Filter by Employee or Role/Designation</h3>
        <form method="GET" action="{{ route('admin.task-schedules.index') }}" class="flex flex-wrap items-end gap-3">
            <div>
                <x-input-label value="Employee" class="text-xs" />
                <x-select-input name="user_id" class="mt-1 text-sm" onchange="this.form.role.value=''; this.form.submit()">
                    <option value="">All Employees</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) $userId === (string) $user->id)>{{ $user->name }} @if ($user->designation) ({{ $user->designation }}) @endif</option>
                    @endforeach
                </x-select-input>
            </div>
            <div>
                <x-input-label value="Role / Designation" class="text-xs" />
                <x-select-input name="role" class="mt-1 text-sm" onchange="this.form.user_id.value=''; this.form.submit()">
                    <option value="">All Roles</option>
                    @foreach ($roles as $roleOption)
                        <option value="{{ $roleOption }}" @selected($role === $roleOption)>{{ $roleOption }}</option>
                    @endforeach
                </x-select-input>
            </div>
            @if ($userId || $role)
                <a href="{{ route('admin.task-schedules.index') }}" class="text-sm font-medium text-gray-500 hover:underline">Clear Filter</a>
            @endif
        </form>
    </x-card>

    <x-card :padded="false">
        @if ($schedules->isEmpty())
            <div class="p-6">
                <x-empty-state icon="calendar-check"
                    title="{{ $userId || $role ? 'No calendar tasks for this filter' : 'No calendar tasks configured' }}"
                    description="{{ $userId || $role ? 'Nothing assigned yet for this employee or role.' : 'Create a recurring task for a user or an entire designation.' }}" />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">User / Designation</th>
                            <th class="px-5 py-3">Task</th>
                            <th class="px-5 py-3">Frequency</th>
                            <th class="px-5 py-3">Due Date/Day</th>
                            <th class="px-5 py-3">Verifier</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @php
                            $weekdays = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                            $months = ['', 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                        @endphp
                        @foreach ($schedules as $schedule)
                            <tr>
                                <td class="px-5 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $schedule->assignedToUser?->name ?? $schedule->assignee_role.' (designation)' }}</td>
                                <td class="px-5 py-3">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $schedule->title }}</span>
                                    @if ($schedule->description)
                                        <p class="text-xs text-gray-400">{{ Str::limit($schedule->description, 60) }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3"><x-badge color="indigo" :status="$schedule->frequency" /></td>
                                <td class="px-5 py-3 text-sm text-gray-500">
                                    @if ($schedule->frequency === 'daily') Every day
                                    @elseif ($schedule->frequency === 'weekly') Every {{ $weekdays[$schedule->day_of_week] ?? '—' }}
                                    @elseif ($schedule->frequency === 'monthly') Day {{ $schedule->day_of_month }} of every month
                                    @else {{ $months[$schedule->month_of_year] ?? '—' }} {{ $schedule->day_of_month }}
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-500">{{ $schedule->verifier?->name ?? '—' }}</td>
                                <td class="px-5 py-3">
                                    <x-badge :status="$schedule->is_active ? 'active' : 'inactive'" />
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm">
                                    <a href="{{ route('admin.task-schedules.edit', $schedule) }}" class="font-medium text-indigo-600 hover:underline">Edit</a>
                                    <form method="POST" action="{{ route('admin.task-schedules.destroy', $schedule) }}" onsubmit="return confirm('Remove this calendar task? Already-generated task instances are kept.')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="ml-2 font-medium text-rose-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $schedules->links() }}</div>
</x-app-layout>
