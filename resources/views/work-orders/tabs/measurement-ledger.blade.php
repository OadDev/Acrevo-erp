<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <x-card>
        <h3 class="mb-4 text-sm font-semibold text-gray-500">Measurement Books — Work Done</h3>
        @forelse ($workOrder->measurementBooks as $mb)
            <div class="border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                <p class="font-medium text-gray-800 dark:text-gray-200">{{ $mb->mb_no }} — {{ $mb->date->format('d M Y') }}</p>
                <p class="text-gray-500">{{ $mb->description }}</p>

                @if ($mb->items->isNotEmpty())
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-xs dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-gray-400">
                                    <th class="py-1 pr-2">Work Description</th>
                                    <th class="py-1 pr-2">L</th>
                                    <th class="py-1 pr-2">B</th>
                                    <th class="py-1 pr-2">D</th>
                                    <th class="py-1 pr-2">Total Nos</th>
                                    <th class="py-1 pr-2">Unit</th>
                                    <th class="py-1 pr-2 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @foreach ($mb->items as $item)
                                    <tr>
                                        <td class="py-1 pr-2">{{ $item->item_description }}</td>
                                        <td class="py-1 pr-2">{{ $item->length ?? '—' }}</td>
                                        <td class="py-1 pr-2">{{ $item->breadth ?? '—' }}</td>
                                        <td class="py-1 pr-2">{{ $item->height ?? '—' }}</td>
                                        <td class="py-1 pr-2">{{ $item->quantity }}</td>
                                        <td class="py-1 pr-2">{{ $item->unit }}</td>
                                        <td class="py-1 pr-2 text-right">₹{{ number_format($item->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="mt-1 text-xs text-gray-400">{{ $mb->items->count() }} item(s) · ₹{{ number_format($mb->items->sum('amount'), 2) }}</p>

                @can('site_records.manage')
                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs font-medium text-indigo-600">+ Add work done entry</summary>
                        <form method="POST" action="{{ route('work-orders.measurement-books.items.store', [$workOrder, $mb]) }}" class="mt-2 grid grid-cols-3 gap-2">
                            @csrf
                            <x-text-input name="item_description" placeholder="Work description" class="col-span-3 text-xs" required />
                            <x-text-input type="number" step="0.01" name="length" placeholder="L" class="text-xs" />
                            <x-text-input type="number" step="0.01" name="breadth" placeholder="B" class="text-xs" />
                            <x-text-input type="number" step="0.01" name="height" placeholder="D" class="text-xs" />
                            <x-text-input type="number" step="0.01" name="quantity" placeholder="Total Nos" class="text-xs" required />
                            <x-text-input name="unit" placeholder="Unit (Sqft, Nos...)" class="text-xs" required />
                            <x-text-input type="number" step="0.01" name="rate" placeholder="Rate (optional)" class="text-xs" />
                            <x-primary-button class="col-span-3 justify-center py-1 text-xs">Add Entry</x-primary-button>
                        </form>
                    </details>
                @endcan
            </div>
        @empty
            <x-empty-state icon="file-text" title="No measurement books yet" />
        @endforelse

        @can('site_records.manage')
            <form method="POST" action="{{ route('work-orders.measurement-books.store', $workOrder) }}" class="mt-4 space-y-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                @csrf
                <x-textarea-input name="description" rows="2" class="w-full" placeholder="Description" required></x-textarea-input>
                <x-text-input type="date" name="date" class="w-full" value="{{ now()->format('Y-m-d') }}" required />
                <x-primary-button class="w-full justify-center">Add Measurement Book</x-primary-button>
            </form>
        @endcan
    </x-card>

    <x-card :padded="false">
        <div class="p-4">
            <h3 class="text-sm font-semibold text-gray-500">Worker Attendance</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-xs dark:divide-gray-800">
                <thead>
                    <tr class="text-left text-gray-400">
                        <th class="px-4 py-1">Worker</th>
                        <th class="px-4 py-1">Date</th>
                        <th class="px-4 py-1">In</th>
                        <th class="px-4 py-1">Out</th>
                        <th class="px-4 py-1">Break</th>
                        <th class="px-4 py-1">Hours</th>
                        <th class="px-4 py-1 text-right">Salary</th>
                        <th class="px-4 py-1 text-right">Advance</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($workOrder->attendances as $attendance)
                        <tr>
                            <td class="px-4 py-1">{{ $attendance->employee?->name }}</td>
                            <td class="px-4 py-1">{{ $attendance->date->format('d M') }}</td>
                            <td class="px-4 py-1">{{ $attendance->check_in ?? '—' }}</td>
                            <td class="px-4 py-1">{{ $attendance->check_out ?? '—' }}</td>
                            <td class="px-4 py-1">{{ $attendance->break_minutes ? $attendance->break_minutes.' min' : '—' }}</td>
                            <td class="px-4 py-1">{{ $attendance->hours_worked ?? '—' }}</td>
                            <td class="px-4 py-1 text-right">{{ $attendance->salary ? '₹'.number_format($attendance->salary, 2) : '—' }}</td>
                            <td class="px-4 py-1 text-right">{{ $attendance->advance ? '₹'.number_format($attendance->advance, 2) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">No attendance recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @can('site_records.manage')
            <form method="POST" action="{{ route('work-orders.attendance.store', $workOrder) }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-4">
                @csrf
                <x-select-input name="employee_id" class="col-span-2 text-sm" required>
                    <option value="">Worker</option>
                    @foreach ($activeEmployees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="status" class="col-span-2 text-sm">
                    <option value="present">Present</option>
                    <option value="half_day">Half Day</option>
                    <option value="absent">Absent</option>
                    <option value="leave">Leave</option>
                </x-select-input>
                <x-text-input type="date" name="date" class="text-sm" value="{{ now()->format('Y-m-d') }}" required />
                <x-text-input type="time" name="check_in" placeholder="In Time" class="text-sm" />
                <x-text-input type="time" name="check_out" placeholder="Out Time" class="text-sm" />
                <x-text-input type="number" min="0" name="break_minutes" placeholder="Lunch/Break (mins)" class="text-sm" />
                <x-text-input type="number" step="0.01" name="salary" placeholder="Salary" class="text-sm" />
                <x-text-input type="number" step="0.01" name="advance" placeholder="Advance" class="col-span-2 text-sm sm:col-span-1" />
                <x-primary-button class="col-span-2 justify-center sm:col-span-4">Record Attendance</x-primary-button>
            </form>
        @endcan
    </x-card>
</div>
