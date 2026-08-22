<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Attendance" subtitle="Mark daily attendance for Sales, HR, Finance, Executive Team Leader, and QC staff.">
            <x-slot name="actions">
                <form method="GET">
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">
                </form>
            </x-slot>
        </x-page-header>
    </x-slot>

    <p class="mb-4 text-xs text-gray-400">Worker attendance isn't marked here — it's recorded per work order, in that work order's Measurement Book tab.</p>

    <form method="POST" action="{{ route('attendance.store') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">

        <x-card :padded="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Staff</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3">Daily Work Details</th>
                            <th class="px-5 py-3 w-28">Salary</th>
                            <th class="px-5 py-3 w-28">Advance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($employees as $employee)
                            @php($current = $employee->attendances->first())
                            <tr>
                                <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $employee->name }} <span class="text-xs text-gray-400">({{ $employee->employee_code }})</span></td>
                                <td class="px-5 py-3">
                                    <select name="attendance[{{ $employee->id }}]" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                                        @foreach (['present' => 'Present', 'absent' => 'Absent', 'half_day' => 'Half Day', 'leave' => 'Leave'] as $value => $label)
                                            <option value="{{ $value }}" @selected(($current->status ?? 'present') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </td>
                                <td class="px-5 py-3">
                                    <input type="text" name="work_details[{{ $employee->id }}]" value="{{ $current->work_details ?? '' }}" placeholder="What did they work on today?" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                                </td>
                                <td class="px-5 py-3">
                                    <input type="number" step="0.01" min="0" name="salary[{{ $employee->id }}]" value="{{ $current->salary ?? '' }}" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                                </td>
                                <td class="px-5 py-3">
                                    <input type="number" step="0.01" min="0" name="advance[{{ $employee->id }}]" value="{{ $current->advance ?? '' }}" class="w-full rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-5 py-6 text-center text-gray-400">No active staff to mark attendance for.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <div class="mt-4">
            <x-primary-button>Save Attendance</x-primary-button>
        </div>
    </form>

    <x-card :padded="false" class="mt-8">
        <div class="flex flex-wrap items-end justify-between gap-4 p-4">
            <div>
                <h3 class="text-sm font-semibold text-gray-500">Attendance Records</h3>
                <p class="mt-1 text-xs text-gray-400">Filter by staff member and date range to review or export attendance history.</p>
            </div>
            <form method="GET" action="{{ route('attendance.index') }}" class="flex flex-wrap items-end gap-2">
                <input type="hidden" name="date" value="{{ $date }}">
                <div>
                    <label class="mb-1 block text-xs text-gray-400">Staff</label>
                    <select name="employee_id" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                        <option value="">All</option>
                        @foreach ($filterEmployees as $fe)
                            <option value="{{ $fe->id }}" @selected($filterEmployeeId == $fe->id)>{{ $fe->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-400">From</label>
                    <input type="date" name="from" value="{{ $from }}" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-gray-400">To</label>
                    <input type="date" name="to" value="{{ $to }}" class="rounded-md border-gray-300 text-sm dark:border-gray-700 dark:bg-gray-900">
                </div>
                <button type="submit" class="rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700 dark:text-gray-200">Filter</button>
                @if ($filterEmployeeId)
                    <a href="{{ route('attendance.pdf', ['employee_id' => $filterEmployeeId, 'from' => $from, 'to' => $to]) }}" class="inline-flex items-center gap-1 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                        <x-icon name="download" class="h-4 w-4" /> PDF
                    </a>
                @endif
            </form>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3">Staff</th>
                        <th class="px-5 py-3">Status</th>
                        <th class="px-5 py-3">Work Details</th>
                        <th class="px-5 py-3 text-right">Salary</th>
                        <th class="px-5 py-3 text-right">Advance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($history as $record)
                        <tr>
                            <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $record->date->format('d M Y') }}</td>
                            <td class="px-5 py-3 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $record->employee->name }}</td>
                            <td class="px-5 py-3"><x-badge :status="$record->status" /></td>
                            <td class="px-5 py-3 text-sm text-gray-500">{{ $record->work_details ?? '—' }}</td>
                            <td class="px-5 py-3 text-right text-sm">{{ $record->salary ? '₹'.number_format($record->salary, 2) : '—' }}</td>
                            <td class="px-5 py-3 text-right text-sm text-gray-500">{{ $record->advance ? '₹'.number_format($record->advance, 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-5 py-6 text-center text-gray-400">No attendance records for this range.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>
</x-app-layout>
