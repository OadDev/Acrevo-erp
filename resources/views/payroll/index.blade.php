<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Payroll" :subtitle="\Carbon\Carbon::create($year, $month, 1)->format('F Y')">
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
        </x-page-header>
    </x-slot>

    <x-card :padded="false" class="mb-6">
        <div class="p-4">
            <h3 class="text-sm font-semibold text-gray-500">Attendance Summary — {{ \Carbon\Carbon::create($year, $month, 1)->format('F Y') }}</h3>
            <p class="mt-1 text-xs text-gray-400">Totals come from Worker Attendance recorded against each work order this month.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Worker</th>
                        <th class="px-4 py-3">Days Present</th>
                        <th class="px-4 py-3 text-right">Total Salary</th>
                        <th class="px-4 py-3 text-right">Total Advance</th>
                        <th class="px-4 py-3"></th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($employees as $employee)
                        @php
                            $records = $attendanceByEmployee->get($employee->id, collect());
                            $totalSalary = $records->sum('salary');
                            $totalAdvance = $records->sum('advance');
                        @endphp
                        @if ($records->isNotEmpty())
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $employee->name }}</td>
                                <td class="px-4 py-3 text-sm text-gray-500">{{ $records->pluck('date')->map->format('Y-m-d')->unique()->count() }}</td>
                                <td class="px-4 py-3 text-right text-sm font-medium">₹{{ number_format($totalSalary, 2) }}</td>
                                <td class="px-4 py-3 text-right text-sm text-gray-500">₹{{ number_format($totalAdvance, 2) }}</td>
                                <td class="px-4 py-3">
                                    <details>
                                        <summary class="cursor-pointer text-xs font-medium text-indigo-600">Day-by-day</summary>
                                        <table class="mt-2 min-w-full text-xs">
                                            <thead>
                                                <tr class="text-left text-gray-400">
                                                    <th class="pr-3 py-1">Date</th>
                                                    <th class="pr-3 py-1">Work Order</th>
                                                    <th class="pr-3 py-1">Hours</th>
                                                    <th class="pr-3 py-1 text-right">Salary</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($records as $record)
                                                    <tr>
                                                        <td class="pr-3 py-1">{{ $record->date->format('d M') }}</td>
                                                        <td class="pr-3 py-1">{{ $record->workOrder?->work_order_no ?? '—' }}</td>
                                                        <td class="pr-3 py-1">{{ $record->hours_worked ?? '—' }}</td>
                                                        <td class="pr-3 py-1 text-right">{{ $record->salary ? '₹'.number_format($record->salary, 2) : '—' }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </details>
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <form method="POST" action="{{ route('payroll.generate-from-attendance') }}">
                                        @csrf
                                        <input type="hidden" name="employee_id" value="{{ $employee->id }}">
                                        <input type="hidden" name="month" value="{{ $month }}">
                                        <input type="hidden" name="year" value="{{ $year }}">
                                        <button class="text-sm text-indigo-600 hover:underline">Generate Payroll</button>
                                    </form>
                                </td>
                            </tr>
                        @endif
                    @empty
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-card>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card :padded="false" class="lg:col-span-2">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">Worker</th>
                            <th class="px-4 py-3 text-right">Net Salary</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($payrolls as $payroll)
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-gray-800 dark:text-gray-200">{{ $payroll->employee->name }}</td>
                                <td class="px-4 py-3 text-right text-sm font-medium">₹{{ number_format($payroll->net_salary, 2) }}</td>
                                <td class="px-4 py-3"><x-badge :status="$payroll->status" /></td>
                                <td class="px-4 py-3 text-right">
                                    @if ($payroll->status === 'pending')
                                        <form method="POST" action="{{ route('payroll.mark-paid', $payroll) }}">
                                            @csrf
                                            <button class="text-sm text-emerald-600 hover:underline">Mark Paid</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No payroll processed for this period.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Process Payroll</h3>
            <form method="POST" action="{{ route('payroll.store') }}" class="space-y-3">
                @csrf
                <input type="hidden" name="month" value="{{ $month }}">
                <input type="hidden" name="year" value="{{ $year }}">
                <x-select-input name="employee_id" class="w-full" required>
                    @foreach ($employees as $employee)
                        <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                    @endforeach
                </x-select-input>
                <x-text-input type="number" step="0.01" name="basic_salary" placeholder="Basic Salary" class="w-full" required />
                <x-text-input type="number" step="0.01" name="allowances" placeholder="Allowances" class="w-full" />
                <x-text-input type="number" step="0.01" name="overtime_amount" placeholder="Overtime" class="w-full" />
                <x-text-input type="number" step="0.01" name="incentive" placeholder="Incentive" class="w-full" />
                <x-text-input type="number" step="0.01" name="deductions" placeholder="Deductions" class="w-full" />
                <x-text-input type="number" step="0.01" name="advance_deducted" placeholder="Advance Deducted" class="w-full" />
                <x-primary-button class="w-full justify-center">Save Payroll</x-primary-button>
            </form>
        </x-card>
    </div>
</x-app-layout>
