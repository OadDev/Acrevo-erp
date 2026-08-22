<x-app-layout>
    <x-slot name="header">
        <x-page-header title="My Attendance" :subtitle="\Carbon\Carbon::create($year, $month, 1)->format('F Y')">
            @if ($employee)
                <x-slot name="actions">
                    <form method="GET" class="flex gap-2">
                        <x-select-input name="month" class="text-sm" onchange="this.form.submit()">
                            @foreach (range(1,12) as $m)
                                <option value="{{ $m }}" @selected($m == $month)>{{ \Carbon\Carbon::create()->month($m)->format('M') }}</option>
                            @endforeach
                        </x-select-input>
                        <x-select-input name="year" class="text-sm" onchange="this.form.submit()">
                            @foreach (range(now()->year - 2, now()->year) as $y)
                                <option value="{{ $y }}" @selected($y == $year)>{{ $y }}</option>
                            @endforeach
                        </x-select-input>
                    </form>
                </x-slot>
            @endif
        </x-page-header>
    </x-slot>

    @if (! $employee)
        <x-empty-state icon="calendar-check" title="No worker profile linked" description="Your login isn't linked to an HR worker record yet. Ask an Admin to link it before attendance can appear here." />
    @else
        @php
            $daysPresent = $attendances->where('status', 'present')->count();
            $daysHalf = $attendances->where('status', 'half_day')->count();
            $daysAbsent = $attendances->where('status', 'absent')->count();
            $daysLeave = $attendances->where('status', 'leave')->count();
        @endphp

        <div class="mb-6 grid grid-cols-2 gap-4 sm:grid-cols-4">
            <x-card class="text-center">
                <p class="text-2xl font-semibold text-emerald-600">{{ $daysPresent }}</p>
                <p class="text-xs text-gray-400">Present</p>
            </x-card>
            <x-card class="text-center">
                <p class="text-2xl font-semibold text-amber-600">{{ $daysHalf }}</p>
                <p class="text-xs text-gray-400">Half Day</p>
            </x-card>
            <x-card class="text-center">
                <p class="text-2xl font-semibold text-red-600">{{ $daysAbsent }}</p>
                <p class="text-xs text-gray-400">Absent</p>
            </x-card>
            <x-card class="text-center">
                <p class="text-2xl font-semibold text-gray-500">{{ $daysLeave }}</p>
                <p class="text-xs text-gray-400">Leave</p>
            </x-card>
        </div>

        <x-card :padded="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-2">Date</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Work Order</th>
                            <th class="px-4 py-2">Work Details</th>
                            <th class="px-4 py-2 text-right">Salary</th>
                            <th class="px-4 py-2 text-right">Advance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($attendances as $record)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $record->date->format('d M Y') }}</td>
                                <td class="px-4 py-2"><x-badge :status="$record->status" /></td>
                                <td class="px-4 py-2 text-gray-500">{{ $record->workOrder?->work_order_no ?? '—' }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $record->work_details ?? '—' }}</td>
                                <td class="px-4 py-2 text-right">{{ $record->salary ? '₹'.number_format($record->salary, 2) : '—' }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">{{ $record->advance ? '₹'.number_format($record->advance, 2) : '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No attendance recorded for this month.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</x-app-layout>
