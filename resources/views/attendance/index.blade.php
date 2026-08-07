<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Attendance" subtitle="Mark daily attendance for active workers.">
            <x-slot name="actions">
                <form method="GET">
                    <input type="date" name="date" value="{{ $date }}" onchange="this.form.submit()" class="rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">
                </form>
            </x-slot>
        </x-page-header>
    </x-slot>

    <form method="POST" action="{{ route('attendance.store') }}">
        @csrf
        <input type="hidden" name="date" value="{{ $date }}">

        <x-card :padded="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Worker</th>
                            <th class="px-5 py-3">Status</th>
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
                            </tr>
                        @empty
                            <tr><td colspan="2" class="px-5 py-6 text-center text-gray-400">No active workers.</td></tr>
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
