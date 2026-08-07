<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Finance" :subtitle="\Carbon\Carbon::create($year, $month, 1)->format('F Y')" />
    </x-slot>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card><p class="text-sm text-gray-500">Income</p><p class="mt-2 text-2xl font-semibold text-emerald-600">₹{{ number_format($income, 0) }}</p></x-card>
        <x-card><p class="text-sm text-gray-500">Expenses</p><p class="mt-2 text-2xl font-semibold text-rose-600">₹{{ number_format($expenses + $vendorPayments, 0) }}</p></x-card>
        <x-card><p class="text-sm text-gray-500">Net Cash Flow</p><p class="mt-2 text-2xl font-semibold text-indigo-600">₹{{ number_format($income - $expenses - $vendorPayments, 0) }}</p></x-card>
    </div>

    <div x-data="{ tab: 'invoices' }">
        <div class="mb-6 flex gap-1 border-b border-gray-200 dark:border-gray-800">
            @foreach (['invoices' => 'Invoices', 'payments' => 'Client Payments', 'vendor' => 'Vendor Payments', 'expenses' => 'Expenses'] as $key => $label)
                <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500'" class="border-b-2 px-4 py-2.5 text-sm font-medium">{{ $label }}</button>
            @endforeach
        </div>

        <div x-show="tab === 'invoices'">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-card :padded="false" class="lg:col-span-2">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($invoices as $invoice)
                                <tr>
                                    <td class="px-4 py-2">{{ $invoice->invoice_no }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $invoice->client->name }}</td>
                                    <td class="px-4 py-2 text-right font-medium">₹{{ number_format($invoice->total_amount, 2) }}</td>
                                    <td class="px-4 py-2"><x-badge :status="$invoice->status" /></td>
                                </tr>
                            @empty
                                <tr><td class="px-4 py-6 text-center text-gray-400">No invoices yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-card>
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">New Invoice</h3>
                    <form method="POST" action="{{ route('finance.invoices.store') }}" class="space-y-2">
                        @csrf
                        <x-select-input name="client_id" class="w-full text-sm" required>
                            @foreach ($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}</option>@endforeach
                        </x-select-input>
                        <x-select-input name="work_order_id" class="w-full text-sm" required>
                            @foreach ($workOrders as $wo)<option value="{{ $wo->id }}">{{ $wo->work_order_no }}</option>@endforeach
                        </x-select-input>
                        <x-text-input type="number" step="0.01" name="amount" placeholder="Amount" class="w-full text-sm" required />
                        <x-text-input type="number" step="0.01" name="tax_amount" placeholder="Tax Amount" class="w-full text-sm" />
                        <x-text-input type="date" name="due_date" class="w-full text-sm" />
                        <x-primary-button class="w-full justify-center">Create Invoice</x-primary-button>
                    </form>
                </x-card>
            </div>
        </div>

        <div x-show="tab === 'payments'" x-cloak>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-card :padded="false" class="lg:col-span-2">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($payments as $payment)
                                <tr>
                                    <td class="px-4 py-2">{{ $payment->payment_date->format('d M Y') }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $payment->client->name }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ Str::title(str_replace('_',' ',$payment->mode)) }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-emerald-600">₹{{ number_format($payment->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td class="px-4 py-6 text-center text-gray-400">No payments recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-card>
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Record Payment</h3>
                    <form method="POST" action="{{ route('finance.payments.store') }}" class="space-y-2">
                        @csrf
                        <x-select-input name="client_id" class="w-full text-sm" required>
                            @foreach ($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}</option>@endforeach
                        </x-select-input>
                        <x-text-input type="number" step="0.01" name="amount" placeholder="Amount" class="w-full text-sm" required />
                        <x-text-input type="date" name="payment_date" class="w-full text-sm" value="{{ now()->format('Y-m-d') }}" required />
                        <x-select-input name="mode" class="w-full text-sm">
                            @foreach (['cash','bank_transfer','upi','cheque','card'] as $mode)<option value="{{ $mode }}">{{ Str::title(str_replace('_',' ',$mode)) }}</option>@endforeach
                        </x-select-input>
                        <x-text-input name="reference_no" placeholder="Reference No." class="w-full text-sm" />
                        <x-primary-button class="w-full justify-center">Record Payment</x-primary-button>
                    </form>
                </x-card>
            </div>
        </div>

        <div x-show="tab === 'vendor'" x-cloak>
            <x-card>
                <h3 class="mb-3 text-sm font-semibold text-gray-500">Record Vendor Payment</h3>
                <form method="POST" action="{{ route('finance.vendor-payments.store') }}" class="grid grid-cols-2 gap-2">
                    @csrf
                    <x-text-input name="vendor_name" placeholder="Vendor Name" class="col-span-2 text-sm" required />
                    <x-text-input type="number" step="0.01" name="amount" placeholder="Amount" class="text-sm" required />
                    <x-text-input type="date" name="payment_date" class="text-sm" value="{{ now()->format('Y-m-d') }}" required />
                    <x-text-input name="category" placeholder="Category" class="text-sm" />
                    <x-select-input name="mode" class="text-sm">
                        @foreach (['cash','bank_transfer','upi','cheque','card'] as $mode)<option value="{{ $mode }}">{{ Str::title(str_replace('_',' ',$mode)) }}</option>@endforeach
                    </x-select-input>
                    <x-primary-button class="col-span-2 justify-center">Save</x-primary-button>
                </form>
            </x-card>
        </div>

        <div x-show="tab === 'expenses'" x-cloak>
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-card :padded="false" class="lg:col-span-2">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($recentExpenses as $expense)
                                <tr>
                                    <td class="px-4 py-2">{{ $expense->expense_date->format('d M Y') }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $expense->category }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-rose-600">₹{{ number_format($expense->amount, 2) }}</td>
                                </tr>
                            @empty
                                <tr><td class="px-4 py-6 text-center text-gray-400">No expenses recorded yet.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </x-card>
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Record Expense</h3>
                    <form method="POST" action="{{ route('finance.expenses.store') }}" class="space-y-2">
                        @csrf
                        <x-text-input name="category" placeholder="Category" class="w-full text-sm" required />
                        <x-text-input type="number" step="0.01" name="amount" placeholder="Amount" class="w-full text-sm" required />
                        <x-text-input type="date" name="expense_date" class="w-full text-sm" value="{{ now()->format('Y-m-d') }}" required />
                        <x-textarea-input name="description" rows="2" class="w-full text-sm" placeholder="Description"></x-textarea-input>
                        <x-primary-button class="w-full justify-center">Save</x-primary-button>
                    </form>
                </x-card>
            </div>
        </div>
    </div>
</x-app-layout>
