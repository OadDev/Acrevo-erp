@php
    $scheduleBooks = $workOrder->measurementBooks->where('type', 'schedule');
    $actualBooks = $workOrder->measurementBooks->where('type', 'actual');
@endphp

@if ($scheduleBooks->isNotEmpty())
    <x-card class="mb-6">
        <h3 class="mb-1 text-sm font-semibold text-gray-500">Allocated Work Schedule (M.Book)</h3>
        <p class="mb-3 text-xs text-gray-400">Planned at work order creation — for reference only, not actual work done.</p>
        @foreach ($scheduleBooks as $mb)
            <div class="border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                <p class="font-medium text-gray-800 dark:text-gray-200">{{ $mb->mb_no }} — {{ $mb->date->format('d M Y') }}</p>
                @if ($mb->items->isNotEmpty())
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-xs dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-gray-400">
                                    <th class="py-1 pr-2">Work Description</th>
                                    <th class="py-1 pr-2">L</th>
                                    <th class="py-1 pr-2">B</th>
                                    <th class="py-1 pr-2">D</th>
                                    <th class="py-1 pr-2">Total</th>
                                    <th class="py-1 pr-2">Unit</th>
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
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endforeach
    </x-card>
@endif

<x-card x-data="{ editMb: null, editItem: null }" class="mb-6">
        <h3 class="mb-4 text-sm font-semibold text-gray-500">Measurement Book — Actual Work Done</h3>
        @forelse ($actualBooks as $mb)
            @php
                $workersPresent = $workOrder->attendances->filter(fn ($a) => $a->date->isSameDay($mb->date));
            @endphp
            <div class="border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                <div class="flex items-center justify-between">
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $mb->mb_no }} — {{ $mb->date->format('d M Y') }}</p>
                    @if (auth()->user()->hasRole('Admin'))
                        <div class="flex items-center gap-2">
                            <button type="button" @click="editMb === {{ $mb->id }} ? editMb = null : editMb = {{ $mb->id }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                            <form method="POST" action="{{ route('work-orders.measurement-books.destroy', [$workOrder, $mb]) }}" onsubmit="return confirm('Remove this measurement book and all its items?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs font-medium text-rose-600 hover:underline">Delete</button>
                            </form>
                        </div>
                    @endif
                </div>
                <p class="text-gray-500">{{ $mb->description }}</p>

                @if (auth()->user()->hasRole('Admin'))
                    <form method="POST" action="{{ route('work-orders.measurement-books.update', [$workOrder, $mb]) }}" x-show="editMb === {{ $mb->id }}" x-cloak class="mt-2 space-y-2 rounded-lg border border-gray-100 p-2.5 dark:border-gray-800">
                        @csrf
                        @method('PUT')
                        <x-textarea-input name="description" rows="2" class="w-full text-xs" required>{{ $mb->description }}</x-textarea-input>
                        <x-text-input type="date" name="date" value="{{ $mb->date->format('Y-m-d') }}" class="w-full text-xs" required />
                        <button class="w-full rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Save</button>
                    </form>
                @endif

                @if ($mb->items->isNotEmpty())
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-xs dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-gray-400">
                                    <th class="py-1 pr-2">Work Name</th>
                                    <th class="py-1 pr-2">L</th>
                                    <th class="py-1 pr-2">B</th>
                                    <th class="py-1 pr-2">D/T/H</th>
                                    <th class="py-1 pr-2">Total</th>
                                    <th class="py-1 pr-2">Unit</th>
                                    <th class="py-1 pr-2 text-right">Rate/Unit</th>
                                    <th class="py-1 pr-2 text-right">Total Amount</th>
                                    @if (auth()->user()->hasRole('Admin'))
                                        <th class="py-1 pr-2">Actions</th>
                                    @endif
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
                                        <td class="py-1 pr-2 text-right">₹{{ number_format($item->rate, 2) }}</td>
                                        <td class="py-1 pr-2 text-right">₹{{ number_format($item->amount, 2) }}</td>
                                        @if (auth()->user()->hasRole('Admin'))
                                            <td class="whitespace-nowrap py-1 pr-2">
                                                <button type="button" @click="editItem === {{ $item->id }} ? editItem = null : editItem = {{ $item->id }}" class="font-medium text-indigo-600 hover:underline">Edit</button>
                                                <form method="POST" action="{{ route('work-orders.measurement-books.items.destroy', [$workOrder, $mb, $item]) }}" onsubmit="return confirm('Remove this work done entry?')" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="ml-2 font-medium text-rose-600 hover:underline">Delete</button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                    @if (auth()->user()->hasRole('Admin'))
                                        <tr x-show="editItem === {{ $item->id }}" x-cloak>
                                            <td colspan="9" class="bg-gray-50 py-2 pr-2 dark:bg-gray-900">
                                                <form method="POST" action="{{ route('work-orders.measurement-books.items.update', [$workOrder, $mb, $item]) }}" class="grid grid-cols-3 gap-2">
                                                    @csrf
                                                    @method('PUT')
                                                    <x-text-input name="item_description" value="{{ $item->item_description }}" placeholder="Work name" class="col-span-3 text-xs" required />
                                                    <x-text-input type="number" step="0.01" name="length" value="{{ $item->length }}" placeholder="L" class="text-xs" />
                                                    <x-text-input type="number" step="0.01" name="breadth" value="{{ $item->breadth }}" placeholder="B" class="text-xs" />
                                                    <x-text-input type="number" step="0.01" name="height" value="{{ $item->height }}" placeholder="D/T/H" class="text-xs" />
                                                    <x-text-input type="number" step="0.01" name="quantity" value="{{ $item->quantity }}" placeholder="Total" class="text-xs" required />
                                                    <x-text-input name="unit" value="{{ $item->unit }}" placeholder="Unit" class="text-xs" required />
                                                    <x-text-input type="number" step="0.01" name="rate" value="{{ $item->rate }}" placeholder="Rate per unit" class="text-xs" />
                                                    <button class="col-span-3 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Save</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endif
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="mt-1 text-xs text-gray-400">{{ $mb->items->count() }} item(s) · ₹{{ number_format($mb->items->sum('amount'), 2) }}</p>

                <div class="mt-2 text-xs text-gray-500">
                    <span class="font-medium text-gray-600 dark:text-gray-400">Workers Attendance:</span>
                    @if ($workersPresent->isNotEmpty())
                        {{ $workersPresent->map(fn ($a) => $a->employee?->name)->filter()->join(', ') }}
                    @else
                        <span class="text-gray-400">No attendance recorded for this date.</span>
                    @endif
                </div>

                @can('site_records.manage')
                    <details class="mt-2">
                        <summary class="cursor-pointer text-xs font-medium text-indigo-600">+ Add work done entry</summary>
                        <form method="POST" action="{{ route('work-orders.measurement-books.items.store', [$workOrder, $mb]) }}" class="mt-2 grid grid-cols-3 gap-2">
                            @csrf
                            <x-text-input name="item_description" placeholder="Work name" class="col-span-3 text-xs" required />
                            <x-text-input type="number" step="0.01" name="length" placeholder="L" class="text-xs" />
                            <x-text-input type="number" step="0.01" name="breadth" placeholder="B" class="text-xs" />
                            <x-text-input type="number" step="0.01" name="height" placeholder="D/T/H" class="text-xs" />
                            <x-text-input type="number" step="0.01" name="quantity" placeholder="Total" class="text-xs" required />
                            <x-text-input name="unit" placeholder="Unit (Sqft, Nos...)" class="text-xs" required />
                            <x-text-input type="number" step="0.01" name="rate" placeholder="Rate per unit" class="text-xs" />
                            <x-primary-button class="col-span-3 justify-center py-1 text-xs">Add Entry</x-primary-button>
                        </form>
                    </details>
                @endcan
            </div>
        @empty
            <x-empty-state icon="file-text" title="No work done recorded yet" />
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

    @php
        $attendanceWorkers = $workOrder->attendances->pluck('employee')->filter()->unique('id')->sortBy('name');
        $filteredAttendances = $workOrder->attendances
            ->when(request('att_worker_id'), fn ($c) => $c->filter(fn ($a) => (string) $a->employee_id === request('att_worker_id')))
            ->when(request('att_from'), fn ($c) => $c->filter(fn ($a) => $a->date->format('Y-m-d') >= request('att_from')))
            ->when(request('att_to'), fn ($c) => $c->filter(fn ($a) => $a->date->format('Y-m-d') <= request('att_to')));
    @endphp
    <x-card :padded="false" x-data="{ editAttendance: null }">
        <div class="p-4">
            <h3 class="text-sm font-semibold text-gray-500">Worker Attendance</h3>
            <p class="mt-1 text-xs text-gray-400">Kept separate from the Measurement Book above, full width, so there's enough room to enter and review attendance details.</p>
        </div>

        <form method="GET" action="{{ route('work-orders.show', $workOrder) }}#mb" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-5">
            <input type="hidden" name="tab" value="mb">
            <x-select-input name="att_worker_id" class="text-sm">
                <option value="">All Workers</option>
                @foreach ($attendanceWorkers as $worker)
                    <option value="{{ $worker->id }}" @selected(request('att_worker_id') == $worker->id)>{{ $worker->name }}</option>
                @endforeach
            </x-select-input>
            <x-text-input type="date" name="att_from" value="{{ request('att_from') }}" placeholder="From" class="text-sm" />
            <x-text-input type="date" name="att_to" value="{{ request('att_to') }}" placeholder="To" class="text-sm" />
            <x-primary-button class="justify-center">Filter</x-primary-button>
            @if (request('att_worker_id'))
                <a href="{{ route('work-orders.attendance.pdf', array_filter([
                    'workOrder' => $workOrder,
                    'employee_id' => request('att_worker_id'),
                    'from' => request('att_from'),
                    'to' => request('att_to'),
                ])) }}" class="inline-flex items-center justify-center gap-1.5 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-500">
                    <x-icon name="download" class="h-4 w-4" /> PDF
                </a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 text-xs dark:divide-gray-800">
                <thead>
                    <tr class="text-left text-gray-400">
                        <th class="px-4 py-2">Worker</th>
                        <th class="px-4 py-2">Date</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2">In</th>
                        <th class="px-4 py-2">Out</th>
                        <th class="px-4 py-2">Break</th>
                        <th class="px-4 py-2">Hours</th>
                        <th class="px-4 py-2 text-right">Salary</th>
                        <th class="px-4 py-2 text-right">Advance</th>
                        @if (auth()->user()->hasRole('Admin'))
                            <th class="px-4 py-2">Actions</th>
                        @endif
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($filteredAttendances as $attendance)
                        <tr>
                            <td class="px-4 py-2">{{ $attendance->employee?->name }}</td>
                            <td class="px-4 py-2">{{ $attendance->date->format('d M') }}</td>
                            <td class="px-4 py-2"><x-badge :status="$attendance->status" /></td>
                            <td class="px-4 py-2">{{ $attendance->check_in ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $attendance->check_out ?? '—' }}</td>
                            <td class="px-4 py-2">{{ $attendance->break_minutes ? $attendance->break_minutes.' min' : '—' }}</td>
                            <td class="px-4 py-2">{{ $attendance->hours_worked ?? '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ $attendance->salary ? '₹'.number_format($attendance->salary, 2) : '—' }}</td>
                            <td class="px-4 py-2 text-right">{{ $attendance->advance ? '₹'.number_format($attendance->advance, 2) : '—' }}</td>
                            @if (auth()->user()->hasRole('Admin'))
                                <td class="whitespace-nowrap px-4 py-2">
                                    <button type="button" @click="editAttendance === {{ $attendance->id }} ? editAttendance = null : editAttendance = {{ $attendance->id }}" class="font-medium text-indigo-600 hover:underline">Edit</button>
                                    <form method="POST" action="{{ route('work-orders.attendance.destroy', [$workOrder, $attendance]) }}" onsubmit="return confirm('Remove this attendance entry?')" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button class="ml-2 font-medium text-rose-600 hover:underline">Delete</button>
                                    </form>
                                </td>
                            @endif
                        </tr>
                        @if (auth()->user()->hasRole('Admin'))
                            <tr x-show="editAttendance === {{ $attendance->id }}" x-cloak>
                                <td colspan="10" class="bg-gray-50 px-4 py-2 dark:bg-gray-900">
                                    <form method="POST" action="{{ route('work-orders.attendance.update', [$workOrder, $attendance]) }}" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                        @csrf
                                        @method('PUT')
                                        <x-text-input type="date" name="date" value="{{ $attendance->date->format('Y-m-d') }}" class="text-xs" required />
                                        <x-select-input name="status" class="text-xs">
                                            <option value="present" @selected($attendance->status === 'present')>Present</option>
                                            <option value="half_day" @selected($attendance->status === 'half_day')>Half Day</option>
                                            <option value="absent" @selected($attendance->status === 'absent')>Absent</option>
                                            <option value="leave" @selected($attendance->status === 'leave')>Leave</option>
                                        </x-select-input>
                                        <x-text-input type="time" name="check_in" value="{{ $attendance->check_in }}" class="text-xs" />
                                        <x-text-input type="time" name="check_out" value="{{ $attendance->check_out }}" class="text-xs" />
                                        <x-text-input type="number" min="0" name="break_minutes" value="{{ $attendance->break_minutes }}" placeholder="Break (mins)" class="text-xs" />
                                        <x-text-input type="number" step="0.01" name="salary" value="{{ $attendance->salary }}" placeholder="Salary" class="text-xs" />
                                        <x-text-input type="number" step="0.01" name="advance" value="{{ $attendance->advance }}" placeholder="Advance" class="text-xs" />
                                        <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Save</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr><td colspan="10" class="px-4 py-6 text-center text-gray-400">{{ request()->hasAny(['att_worker_id', 'att_from', 'att_to']) ? 'No attendance matches this filter.' : 'No attendance recorded yet.' }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @can('site_records.manage')
            <form method="POST" action="{{ route('work-orders.attendance.store', $workOrder) }}" class="grid grid-cols-2 gap-3 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-4 lg:grid-cols-9">
                @csrf
                <x-select-input name="employee_id" class="col-span-2 text-sm" required>
                    <option value="">Worker</option>
                    @foreach ($activeEmployees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                    @endforeach
                </x-select-input>
                <x-select-input name="status" class="col-span-2 text-sm lg:col-span-1">
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
                <x-text-input type="number" step="0.01" name="advance" placeholder="Advance" class="text-sm" />
                <x-primary-button class="col-span-2 justify-center sm:col-span-4 lg:col-span-9">Record Attendance</x-primary-button>
            </form>
        @endcan
    </x-card>
