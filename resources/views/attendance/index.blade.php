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
</x-app-layout>
