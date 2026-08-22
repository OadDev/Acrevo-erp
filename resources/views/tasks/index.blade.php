<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Tasks" subtitle="Common one-off tasks and recurring calendar tasks.">
            <x-slot name="actions">
                @can('tasks.create')
                    <x-link-button :href="route('tasks.create')">+ Assign Task</x-link-button>
                @endcan
                @can('tasks.manage')
                    <x-link-button :href="route('admin.task-schedules.index')" variant="secondary">Calendar Task Management</x-link-button>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @php
            $baseQuery = array_filter(['type' => $type, 'status' => $status]);
        @endphp
        <a href="{{ route('tasks.index', $baseQuery + ['scope' => 'mine']) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $scope === 'mine' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">My Tasks</a>
        <a href="{{ route('tasks.index', $baseQuery + ['scope' => 'assigned']) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $scope === 'assigned' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">Assigned By Me / To Verify</a>
        @can('tasks.manage')
            <a href="{{ route('tasks.index', $baseQuery + ['scope' => 'all']) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $scope === 'all' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-300' }}">All Tasks</a>
        @endcan

        <span class="mx-1 h-5 w-px bg-gray-200 dark:bg-gray-700"></span>

        <a href="{{ route('tasks.index', array_filter(['scope' => $scope, 'status' => $status]) ) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ ! $type ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400' : 'text-gray-500' }}">All Types</a>
        <a href="{{ route('tasks.index', array_filter(['scope' => $scope, 'status' => $status]) + ['type' => 'common']) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $type === 'common' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400' : 'text-gray-500' }}">Common Task</a>
        <a href="{{ route('tasks.index', array_filter(['scope' => $scope, 'status' => $status]) + ['type' => 'calendar']) }}" class="rounded-lg px-3 py-1.5 text-sm font-medium {{ $type === 'calendar' ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-400' : 'text-gray-500' }}">Calendar Task</a>

        @php $statusBase = array_filter(['scope' => $scope, 'type' => $type]); @endphp
        <x-select-input name="status" class="ml-auto text-sm" onchange="location.href='{{ route('tasks.index', $statusBase) }}{{ $statusBase ? '&' : '?' }}status='+this.value">
            <option value="">All Statuses</option>
            <option value="pending" @selected($status === 'pending')>Pending</option>
            <option value="overdue" @selected($status === 'overdue')>Overdue</option>
            <option value="submitted" @selected($status === 'submitted')>Submitted (Awaiting Verification)</option>
            <option value="verified" @selected($status === 'verified')>Verified / Completed</option>
            <option value="retasked" @selected($status === 'retasked')>Retasked</option>
        </x-select-input>
    </div>

    @if ($isAdmin)
        <x-card class="mb-6">
            <h3 class="mb-3 text-sm font-semibold text-gray-500">Admin Filter &mdash; Review Task Performance by User</h3>
            <form method="GET" action="{{ route('tasks.index') }}" class="flex flex-wrap items-end gap-3">
                <input type="hidden" name="scope" value="{{ $scope }}">
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="status" value="{{ $status }}">
                <div>
                    <x-input-label value="User" class="text-xs" />
                    <x-select-input name="user_id" class="mt-1 text-sm">
                        <option value="">All Users</option>
                        @foreach ($assignableUsers as $assignable)
                            <option value="{{ $assignable->id }}" @selected((string) $filterUserId === (string) $assignable->id)>{{ $assignable->name }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label value="From" class="text-xs" />
                    <x-text-input type="date" name="from" value="{{ $filterFrom }}" class="mt-1 text-sm" />
                </div>
                <div>
                    <x-input-label value="To" class="text-xs" />
                    <x-text-input type="date" name="to" value="{{ $filterTo }}" class="mt-1 text-sm" />
                </div>
                <x-primary-button>Apply Filter</x-primary-button>
                <a href="{{ route('tasks.pdf', request()->query()) }}" class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-2 text-sm font-semibold text-white hover:bg-emerald-500">
                    <x-icon name="download" class="h-4 w-4" /> Download PDF
                </a>
            </form>
        </x-card>
    @endif

    <x-card :padded="false">
        @if ($tasks->isEmpty())
            <div class="p-6"><x-empty-state icon="clipboard" title="No tasks" description="Nothing here yet." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Date</th>
                            <th class="px-5 py-3">Assigned By</th>
                            <th class="px-5 py-3">Assign To</th>
                            <th class="px-5 py-3">Task Description</th>
                            <th class="px-5 py-3">Due Date</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($tasks as $task)
                            @php
                                $canManageRow = in_array(auth()->id(), [$task->assigned_by, $task->verifier_id], true) || auth()->user()->hasRole('Admin');
                            @endphp
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('tasks.show', $task) }}'">
                                <td class="px-5 py-3 text-sm text-gray-500">{{ $task->created_at->timezone('Asia/Kolkata')->format('d M Y') }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $task->assignedBy?->name ?? 'System (Calendar)' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $task->assignedTo?->name }}</td>
                                <td class="px-5 py-3">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $task->title }}</span>
                                    @if ($task->schedule)
                                        <p class="text-xs text-gray-400">Calendar &middot; {{ Str::title($task->schedule->frequency) }}</p>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-sm {{ $task->isOverdue() ? 'font-semibold text-rose-600' : 'text-gray-600 dark:text-gray-300' }}">{{ $task->due_date->format('d M Y') }}</td>
                                <td class="px-5 py-3">
                                    @if ($task->isOverdue())
                                        <x-badge status="overdue" />
                                    @else
                                        <x-badge :status="$task->status" />
                                    @endif
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-sm" onclick="event.stopPropagation()">
                                    @if ($canManageRow)
                                        @if (! $task->schedule)
                                            <a href="{{ route('tasks.edit', $task) }}" class="font-medium text-indigo-600 hover:underline">Edit</a>
                                        @endif
                                        <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Remove this task?')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ml-2 font-medium text-rose-600 hover:underline">Remove</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $tasks->links() }}</div>
</x-app-layout>
