<x-app-layout>
    <x-slot name="header">
        <x-page-header title="My Payroll" subtitle="Your salary history and payslips" />
    </x-slot>

    @if (! $employee)
        <x-empty-state icon="wallet" title="No worker profile linked" description="Your login isn't linked to an HR worker record yet. Ask an Admin to link it before payroll can appear here." />
    @else
        <x-card :padded="false">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-2">Month</th>
                            <th class="px-4 py-2 text-right">Net Salary</th>
                            <th class="px-4 py-2 text-right">Paid</th>
                            <th class="px-4 py-2 text-right">Held / Remaining</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Last Payment</th>
                            <th class="px-4 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($payrolls as $payroll)
                            <tr>
                                <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ \Carbon\Carbon::create($payroll->year, $payroll->month, 1)->format('F Y') }}</td>
                                <td class="px-4 py-2 text-right">₹{{ number_format($payroll->net_salary, 2) }}</td>
                                <td class="px-4 py-2 text-right text-gray-500">₹{{ number_format($payroll->paid_amount, 2) }}</td>
                                <td class="px-4 py-2 text-right {{ $payroll->remaining() > 0 ? 'text-amber-600' : 'text-gray-400' }}">₹{{ number_format($payroll->remaining(), 2) }}</td>
                                <td class="px-4 py-2"><x-badge :status="$payroll->status" /></td>
                                <td class="px-4 py-2 text-gray-500">
                                    @php $lastPayment = $payroll->payments->last(); @endphp
                                    {{ $lastPayment ? \Carbon\Carbon::parse($lastPayment->paid_on)->format('d M Y') : '—' }}
                                </td>
                                <td class="px-4 py-2 text-right">
                                    <a href="{{ route('my-payroll.pdf', $payroll) }}" class="text-sm font-medium text-indigo-600 hover:underline">Payslip PDF</a>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No payroll has been processed for you yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-card>
    @endif
</x-app-layout>
