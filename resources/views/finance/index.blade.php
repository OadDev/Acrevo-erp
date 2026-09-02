@php
    $modes = ['cash', 'bank_transfer', 'upi', 'cheque', 'card'];
@endphp
<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Finance" :subtitle="\Carbon\Carbon::create($year, $month, 1)->format('F Y')" />
    </x-slot>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-card><p class="text-sm text-gray-500">Income</p><p class="mt-2 text-2xl font-semibold text-emerald-600">₹{{ number_format($income, 0) }}</p></x-card>
        <x-card><p class="text-sm text-gray-500">Expenses</p><p class="mt-2 text-2xl font-semibold text-rose-600">₹{{ number_format($expenses + $vendorPayments, 0) }}</p></x-card>
        <x-card><p class="text-sm text-gray-500">Net Cash Flow</p><p class="mt-2 text-2xl font-semibold text-indigo-600">₹{{ number_format($income - $expenses - $vendorPayments, 0) }}</p></x-card>
    </div>

    <div x-data="{ tab: '{{ request('tab', 'invoices') }}' }">
        <div class="mb-6 flex gap-1 overflow-x-auto border-b border-gray-200 dark:border-gray-800">
            @foreach (['invoices' => 'Invoices', 'payments' => 'Client Payments', 'vendor' => 'Sub Contractor Payments', 'expenses' => 'Expenses', 'categories' => 'Category List'] as $key => $label)
                <button @click="tab = '{{ $key }}'" :class="tab === '{{ $key }}' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-500'" class="whitespace-nowrap border-b-2 px-4 py-2.5 text-sm font-medium">{{ $label }}</button>
            @endforeach
        </div>

        {{-- ==================== INVOICES ==================== --}}
        <div x-show="tab === 'invoices'" x-data="{ editInvoice: null }">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-card :padded="false" class="lg:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <h3 class="text-sm font-semibold text-gray-500">Invoices</h3>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('finance.invoices.pdf') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                <x-icon name="download" class="h-4 w-4" /> Download PDF
                            </a>
                            <a href="{{ route('finance.invoices.csv') }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                <x-icon name="download" class="h-4 w-4" /> Download CSV
                            </a>
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-2">Invoice</th>
                                    <th class="px-4 py-2">Client User</th>
                                    <th class="px-4 py-2">WO</th>
                                    <th class="px-4 py-2 text-right">Amount</th>
                                    <th class="px-4 py-2 text-right">Paid</th>
                                    <th class="px-4 py-2 text-right">Due</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($invoices as $invoice)
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-gray-900 dark:text-white">{{ $invoice->invoice_no }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $invoice->client?->name ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $invoice->workOrder?->work_order_no ?? '—' }}</td>
                                        <td class="px-4 py-2 text-right font-medium">₹{{ number_format($invoice->total_amount, 2) }}</td>
                                        <td class="px-4 py-2 text-right text-emerald-600">₹{{ number_format($invoice->paidAmount(), 2) }}</td>
                                        <td class="px-4 py-2 text-right text-rose-600">₹{{ number_format($invoice->balanceDue(), 2) }}</td>
                                        <td class="px-4 py-2"><x-badge :status="$invoice->status" /></td>
                                        <td class="whitespace-nowrap px-4 py-2">
                                            <button type="button" @click="editInvoice === {{ $invoice->id }} ? editInvoice = null : editInvoice = {{ $invoice->id }}" class="text-xs font-medium text-indigo-600 hover:underline">Update Status</button>
                                            @can('work_orders.cancel')
                                                <form method="POST" action="{{ route('finance.invoices.destroy', $invoice) }}" onsubmit="return confirm('Remove this invoice? This cannot be undone.')" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Delete</button>
                                                </form>
                                            @endcan
                                        </td>
                                    </tr>
                                    <tr x-show="editInvoice === {{ $invoice->id }}" x-cloak>
                                        <td colspan="8" class="bg-gray-50 px-4 py-3 dark:bg-gray-900">
                                            <form method="POST" action="{{ route('finance.invoices.status', $invoice) }}" class="flex flex-wrap items-center gap-2">
                                                @csrf
                                                @method('PUT')
                                                <x-select-input name="status" class="text-xs">
                                                    @foreach (['draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled'] as $status)
                                                        <option value="{{ $status }}" @selected($invoice->status === $status)>{{ Str::title($status) }}</option>
                                                    @endforeach
                                                </x-select-input>
                                                <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Save Status</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="px-4 py-6 text-center text-gray-400">No invoices yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">New Invoice</h3>
                    @if ($clientUsers->isEmpty())
                        <p class="text-sm text-gray-400">No clients have portal access yet. Generate portal access from a Client's page first, so the invoice has a Client User to be sent to.</p>
                    @else
                        <form method="POST" action="{{ route('finance.invoices.store') }}" class="space-y-2">
                            @csrf
                            <div>
                                <x-input-label value="Client User" />
                                <x-select-input name="client_id" class="mt-1 w-full text-sm" required>
                                    <option value="">Select client user</option>
                                    @foreach ($clientUsers as $user)
                                        <option value="{{ $user->clientLogin->client_id }}">{{ $user->clientLogin->client?->name }} — {{ $user->name }} ({{ $user->email }})</option>
                                    @endforeach
                                </x-select-input>
                            </div>
                            <div>
                                <x-input-label value="Work Order (optional)" />
                                <x-select-input name="work_order_id" class="mt-1 w-full text-sm">
                                    <option value="">No specific work order</option>
                                    @foreach ($workOrders as $wo)<option value="{{ $wo->id }}">{{ $wo->work_order_no }} — {{ $wo->title }}</option>@endforeach
                                </x-select-input>
                            </div>
                            <x-text-input type="number" step="0.01" name="amount" placeholder="Amount" class="w-full text-sm" required />
                            <x-text-input type="number" step="0.01" name="tax_amount" placeholder="Tax Amount" class="w-full text-sm" />
                            <x-text-input type="date" name="due_date" class="w-full text-sm" />
                            <x-primary-button class="w-full justify-center">Create &amp; Send Invoice</x-primary-button>
                        </form>
                    @endif
                </x-card>
            </div>
        </div>

        {{-- ==================== CLIENT PAYMENTS ==================== --}}
        <div x-show="tab === 'payments'" x-cloak x-data="{ editPayment: null }">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-card :padded="false" class="lg:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <h3 class="text-sm font-semibold text-gray-500">Client Payments</h3>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('finance.payments.pdf', request()->only(['payment_client_id', 'payment_from', 'payment_to'])) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                <x-icon name="download" class="h-4 w-4" /> Download PDF
                            </a>
                            <a href="{{ route('finance.payments.csv', request()->only(['payment_client_id', 'payment_from', 'payment_to'])) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                <x-icon name="download" class="h-4 w-4" /> Download CSV
                            </a>
                        </div>
                    </div>
                    <form method="GET" action="{{ route('finance.index') }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-4">
                        <input type="hidden" name="tab" value="payments">
                        <x-select-input name="payment_client_id" class="text-sm">
                            <option value="">All Clients</option>
                            @foreach ($clients as $client)<option value="{{ $client->id }}" @selected(request('payment_client_id') === $client->id)>{{ $client->name }}</option>@endforeach
                        </x-select-input>
                        <x-text-input type="date" name="payment_from" value="{{ request('payment_from') }}" class="text-sm" />
                        <x-text-input type="date" name="payment_to" value="{{ request('payment_to') }}" class="text-sm" />
                        <x-primary-button class="justify-center">Filter</x-primary-button>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-2">Date</th>
                                    <th class="px-4 py-2">Client</th>
                                    <th class="px-4 py-2">Site</th>
                                    <th class="px-4 py-2">WO No.</th>
                                    <th class="px-4 py-2">Mode</th>
                                    <th class="px-4 py-2 text-right">Amount</th>
                                    <th class="px-4 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($payments as $payment)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-500">{{ $payment->payment_date->format('d M Y') }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $payment->client?->name ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $payment->site?->site_no ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $payment->workOrder?->work_order_no ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ Str::title(str_replace('_',' ',$payment->mode)) }}</td>
                                        <td class="px-4 py-2 text-right font-medium text-emerald-600">₹{{ number_format($payment->amount, 2) }}</td>
                                        <td class="whitespace-nowrap px-4 py-2">
                                            <button type="button" @click="editPayment === {{ $payment->id }} ? editPayment = null : editPayment = {{ $payment->id }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                                            <form method="POST" action="{{ route('finance.payments.destroy', $payment) }}" onsubmit="return confirm('Remove this payment?')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr x-show="editPayment === {{ $payment->id }}" x-cloak>
                                        <td colspan="7" class="bg-gray-50 px-4 py-3 dark:bg-gray-900">
                                            <form method="POST" action="{{ route('finance.payments.update', $payment) }}" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                                @csrf
                                                @method('PUT')
                                                <x-select-input name="client_id" class="text-xs">
                                                    @foreach ($clients as $client)<option value="{{ $client->id }}" @selected($payment->client_id === $client->id)>{{ $client->name }}</option>@endforeach
                                                </x-select-input>
                                                <x-select-input name="site_id" class="text-xs">
                                                    <option value="">No site</option>
                                                    @foreach ($sites as $site)<option value="{{ $site->id }}" @selected($payment->site_id === $site->id)>{{ $site->site_no }}</option>@endforeach
                                                </x-select-input>
                                                <x-select-input name="work_order_id" class="text-xs">
                                                    <option value="">No WO</option>
                                                    @foreach ($workOrders as $wo)<option value="{{ $wo->id }}" @selected($payment->work_order_id === $wo->id)>{{ $wo->work_order_no }}</option>@endforeach
                                                </x-select-input>
                                                <x-select-input name="invoice_id" class="text-xs">
                                                    <option value="">No invoice</option>
                                                    @foreach ($invoices as $invoice)<option value="{{ $invoice->id }}" @selected($payment->invoice_id === $invoice->id)>{{ $invoice->invoice_no }}</option>@endforeach
                                                </x-select-input>
                                                <x-text-input type="number" step="0.01" name="amount" value="{{ $payment->amount }}" class="text-xs" required />
                                                <x-text-input type="date" name="payment_date" value="{{ $payment->payment_date->format('Y-m-d') }}" class="text-xs" required />
                                                <x-select-input name="mode" class="text-xs">
                                                    @foreach ($modes as $mode)<option value="{{ $mode }}" @selected($payment->mode === $mode)>{{ Str::title(str_replace('_',' ',$mode)) }}</option>@endforeach
                                                </x-select-input>
                                                <x-text-input name="reference_no" value="{{ $payment->reference_no }}" placeholder="Reference No." class="text-xs" />
                                                <button class="col-span-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 sm:col-span-4">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No payments match this filter.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">{{ $payments->appends(request()->query())->links() }}</div>
                </x-card>
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Record Payment</h3>
                    <form method="POST" action="{{ route('finance.payments.store') }}" class="space-y-2">
                        @csrf
                        <x-select-input name="client_id" class="w-full text-sm" required>
                            <option value="">Select client</option>
                            @foreach ($clients as $client)<option value="{{ $client->id }}">{{ $client->name }}</option>@endforeach
                        </x-select-input>
                        <x-select-input name="site_id" class="w-full text-sm">
                            <option value="">Site (optional)</option>
                            @foreach ($sites as $site)<option value="{{ $site->id }}">{{ $site->site_no }}</option>@endforeach
                        </x-select-input>
                        <x-select-input name="work_order_id" class="w-full text-sm">
                            <option value="">WO Number (optional)</option>
                            @foreach ($workOrders as $wo)<option value="{{ $wo->id }}">{{ $wo->work_order_no }}</option>@endforeach
                        </x-select-input>
                        <x-select-input name="invoice_id" class="w-full text-sm">
                            <option value="">Link to invoice (optional)</option>
                            @foreach ($invoices as $invoice)<option value="{{ $invoice->id }}">{{ $invoice->invoice_no }} — ₹{{ number_format($invoice->balanceDue(), 2) }} due</option>@endforeach
                        </x-select-input>
                        <x-text-input type="number" step="0.01" name="amount" placeholder="Amount" class="w-full text-sm" required />
                        <x-text-input type="date" name="payment_date" class="w-full text-sm" value="{{ now()->format('Y-m-d') }}" required />
                        <x-select-input name="mode" class="w-full text-sm">
                            @foreach ($modes as $mode)<option value="{{ $mode }}">{{ Str::title(str_replace('_',' ',$mode)) }}</option>@endforeach
                        </x-select-input>
                        <x-text-input name="reference_no" placeholder="Reference No." class="w-full text-sm" />
                        <x-primary-button class="w-full justify-center">Record Payment</x-primary-button>
                    </form>
                </x-card>
            </div>
        </div>

        {{-- ==================== SUB CONTRACTOR PAYMENTS ==================== --}}
        <div x-show="tab === 'vendor'" x-cloak x-data="{ editVendor: null }">
            <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
                <x-card :padded="false" class="lg:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <h3 class="text-sm font-semibold text-gray-500">Sub Contractor Payments</h3>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('finance.vendor-payments.pdf', request()->only(['vendor_user_id', 'vendor_work_order_id', 'vendor_from', 'vendor_to'])) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                <x-icon name="download" class="h-4 w-4" /> Download PDF
                            </a>
                            <a href="{{ route('finance.vendor-payments.csv', request()->only(['vendor_user_id', 'vendor_work_order_id', 'vendor_from', 'vendor_to'])) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                                <x-icon name="download" class="h-4 w-4" /> Download CSV
                            </a>
                        </div>
                    </div>
                    <form method="GET" action="{{ route('finance.index') }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-5">
                        <input type="hidden" name="tab" value="vendor">
                        <x-select-input name="vendor_user_id" class="text-sm">
                            <option value="">All Sub Contractors</option>
                            @foreach ($subContractors as $sc)<option value="{{ $sc->id }}" @selected((string) request('vendor_user_id') === (string) $sc->id)>{{ $sc->name }}</option>@endforeach
                        </x-select-input>
                        <x-select-input name="vendor_work_order_id" class="text-sm">
                            <option value="">All Work Orders</option>
                            @foreach ($workOrders as $wo)<option value="{{ $wo->id }}" @selected((string) request('vendor_work_order_id') === (string) $wo->id)>{{ $wo->work_order_no }}</option>@endforeach
                        </x-select-input>
                        <x-text-input type="date" name="vendor_from" value="{{ request('vendor_from') }}" class="text-sm" />
                        <x-text-input type="date" name="vendor_to" value="{{ request('vendor_to') }}" class="text-sm" />
                        <x-primary-button class="justify-center">Filter</x-primary-button>
                    </form>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-2">Date</th>
                                    <th class="px-4 py-2">Sub Contractor</th>
                                    <th class="px-4 py-2">WO</th>
                                    <th class="px-4 py-2">Category</th>
                                    <th class="px-4 py-2 text-right">Amount</th>
                                    <th class="px-4 py-2">Remark</th>
                                    <th class="px-4 py-2">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($vendorPaymentEntries as $vp)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-500">{{ $vp->payment_date->format('d M Y') }}</td>
                                        <td class="px-4 py-2 text-gray-700 dark:text-gray-300">{{ $vp->subContractor?->name ?? $vp->vendor_name }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $vp->workOrder?->work_order_no ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $vp->category ?? '—' }}</td>
                                        <td class="px-4 py-2 text-right font-medium text-rose-600">₹{{ number_format($vp->amount, 2) }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $vp->remark ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-4 py-2">
                                            <button type="button" @click="editVendor === {{ $vp->id }} ? editVendor = null : editVendor = {{ $vp->id }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                                            <form method="POST" action="{{ route('finance.vendor-payments.destroy', $vp) }}" onsubmit="return confirm('Remove this sub-contractor payment?')" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                    <tr x-show="editVendor === {{ $vp->id }}" x-cloak>
                                        <td colspan="7" class="bg-gray-50 px-4 py-3 dark:bg-gray-900">
                                            <form method="POST" action="{{ route('finance.vendor-payments.update', $vp) }}" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                                @csrf
                                                @method('PUT')
                                                <x-select-input name="user_id" class="text-xs">
                                                    @foreach ($subContractors as $sc)<option value="{{ $sc->id }}" @selected($vp->user_id === $sc->id)>{{ $sc->name }}</option>@endforeach
                                                </x-select-input>
                                                <x-select-input name="work_order_id" class="text-xs">
                                                    <option value="">No WO</option>
                                                    @foreach ($workOrders as $wo)<option value="{{ $wo->id }}" @selected($vp->work_order_id === $wo->id)>{{ $wo->work_order_no }}</option>@endforeach
                                                </x-select-input>
                                                <x-text-input type="number" step="0.01" name="amount" value="{{ $vp->amount }}" class="text-xs" required />
                                                <x-text-input type="date" name="payment_date" value="{{ $vp->payment_date->format('Y-m-d') }}" class="text-xs" required />
                                                <x-select-input name="category" class="text-xs">
                                                    <option value="">No category</option>
                                                    @foreach ($ledgerCategories as $ledgerCategory)
                                                        <option value="{{ $ledgerCategory->name }}" @selected($vp->category === $ledgerCategory->name)>{{ $ledgerCategory->name }}</option>
                                                    @endforeach
                                                </x-select-input>
                                                <x-select-input name="mode" class="text-xs">
                                                    @foreach ($modes as $mode)<option value="{{ $mode }}" @selected($vp->mode === $mode)>{{ Str::title(str_replace('_',' ',$mode)) }}</option>@endforeach
                                                </x-select-input>
                                                <x-text-input name="remark" value="{{ $vp->remark }}" placeholder="Remark" class="col-span-2 text-xs" />
                                                <button class="col-span-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 sm:col-span-4">Save</button>
                                            </form>
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No sub-contractor payments match this filter.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4">{{ $vendorPaymentEntries->appends(request()->query())->links() }}</div>
                </x-card>
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Record Sub Contractor Payment</h3>
                    @if ($subContractors->isEmpty())
                        <p class="text-sm text-gray-400">No users hold the Sub Contractor role yet. Create one via Users management first.</p>
                    @else
                        <form method="POST" action="{{ route('finance.vendor-payments.store') }}" class="space-y-2">
                            @csrf
                            <x-select-input name="user_id" class="w-full text-sm" required>
                                <option value="">Select sub-contractor</option>
                                @foreach ($subContractors as $sc)<option value="{{ $sc->id }}">{{ $sc->name }}</option>@endforeach
                            </x-select-input>
                            <x-select-input name="work_order_id" class="w-full text-sm">
                                <option value="">Work Order (optional)</option>
                                @foreach ($workOrders as $wo)<option value="{{ $wo->id }}">{{ $wo->work_order_no }}</option>@endforeach
                            </x-select-input>
                            <x-text-input type="number" step="0.01" name="amount" placeholder="Amount" class="w-full text-sm" required />
                            <x-text-input type="date" name="payment_date" class="w-full text-sm" value="{{ now()->format('Y-m-d') }}" required />
                            <x-select-input name="category" class="w-full text-sm">
                                <option value="">No category</option>
                                @foreach ($ledgerCategories as $ledgerCategory)
                                    <option value="{{ $ledgerCategory->name }}">{{ $ledgerCategory->name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-select-input name="mode" class="w-full text-sm">
                                @foreach ($modes as $mode)<option value="{{ $mode }}">{{ Str::title(str_replace('_',' ',$mode)) }}</option>@endforeach
                            </x-select-input>
                            <x-textarea-input name="remark" rows="2" class="w-full text-sm" placeholder="Remark (optional)"></x-textarea-input>
                            <x-primary-button class="w-full justify-center">Save</x-primary-button>
                        </form>
                    @endif
                </x-card>
            </div>
        </div>

        {{-- ==================== EXPENSES ==================== --}}
        <div x-show="tab === 'expenses'" x-cloak x-data="{ editExpense: null }">
            <x-card class="mb-6">
                <h3 class="text-sm font-semibold text-gray-500">Current Balance</h3>
                <p class="text-2xl font-semibold text-gray-900 dark:text-white">₹{{ number_format($currentExpenseBalance, 2) }}</p>
                <p class="mt-1 text-xs text-gray-400">General company expenses — optionally tied to a work order — salaries, GST filing, office costs, and other overheads.</p>
            </x-card>

            <x-card :padded="false">
                <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                    <h3 class="text-sm font-semibold text-gray-500">Expenses</h3>
                    <div class="flex items-center gap-3">
                        <a href="{{ route('finance.expenses.pdf', request()->only(['expense_from', 'expense_to', 'expense_category', 'expense_type', 'expense_work_order_id'])) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            <x-icon name="download" class="h-4 w-4" /> Download PDF
                        </a>
                        <a href="{{ route('finance.expenses.csv', request()->only(['expense_from', 'expense_to', 'expense_category', 'expense_type', 'expense_work_order_id'])) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
                            <x-icon name="download" class="h-4 w-4" /> Download CSV
                        </a>
                    </div>
                </div>
                <form method="GET" action="{{ route('finance.index') }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-6">
                    <input type="hidden" name="tab" value="expenses">
                    <x-text-input type="date" name="expense_from" value="{{ request('expense_from') }}" class="text-sm" />
                    <x-text-input type="date" name="expense_to" value="{{ request('expense_to') }}" class="text-sm" />
                    <x-select-input name="expense_category" class="text-sm">
                        <option value="">All Categories</option>
                        @foreach ($expenseCategories as $category)
                            <option value="{{ $category }}" @selected(request('expense_category') === $category)>{{ $category }}</option>
                        @endforeach
                    </x-select-input>
                    <x-select-input name="expense_type" class="text-sm">
                        <option value="">All Types</option>
                        <option value="credit" @selected(request('expense_type') === 'credit')>Credit</option>
                        <option value="debit" @selected(request('expense_type') === 'debit')>Debit</option>
                        <option value="borrow" @selected(request('expense_type') === 'borrow')>Borrow</option>
                        <option value="lended" @selected(request('expense_type') === 'lended')>Lended</option>
                    </x-select-input>
                    <x-select-input name="expense_work_order_id" class="text-sm">
                        <option value="">All Work Orders</option>
                        @foreach ($workOrders as $wo)<option value="{{ $wo->id }}" @selected((string) request('expense_work_order_id') === (string) $wo->id)>{{ $wo->work_order_no }}</option>@endforeach
                    </x-select-input>
                    <x-primary-button class="justify-center">Filter</x-primary-button>
                </form>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                                <th class="px-4 py-2">Date</th>
                                <th class="px-4 py-2">Category</th>
                                <th class="px-4 py-2">Description</th>
                                <th class="px-4 py-2">Work Order</th>
                                <th class="px-4 py-2 text-right">Borrow</th>
                                <th class="px-4 py-2 text-right">Credit</th>
                                <th class="px-4 py-2 text-right">Debit</th>
                                <th class="px-4 py-2 text-right">Lended</th>
                                <th class="px-4 py-2 text-right">Balance</th>
                                <th class="px-4 py-2">Bill</th>
                                <th class="px-4 py-2">Remark</th>
                                <th class="px-4 py-2">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                            @forelse ($recentExpenses as $expense)
                                <tr>
                                    <td class="px-4 py-2 text-gray-500">{{ $expense->expense_date->format('d M Y') }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $expense->category }}</td>
                                    <td class="px-4 py-2">{{ $expense->description }}</td>
                                    <td class="px-4 py-2 text-gray-500">{{ $expense->workOrder?->work_order_no ?? '—' }}</td>
                                    <td class="px-4 py-2 text-right text-blue-600">{{ $expense->type === 'borrow' ? '₹'.number_format($expense->amount, 2) : '—' }}</td>
                                    <td class="px-4 py-2 text-right text-emerald-600">{{ $expense->type === 'credit' ? '₹'.number_format($expense->amount, 2) : '—' }}</td>
                                    <td class="px-4 py-2 text-right text-rose-600">{{ $expense->type === 'debit' ? '₹'.number_format($expense->amount, 2) : '—' }}</td>
                                    <td class="px-4 py-2 text-right text-amber-600">{{ $expense->type === 'lended' ? '₹'.number_format($expense->amount, 2) : '—' }}</td>
                                    <td class="px-4 py-2 text-right font-medium text-gray-800 dark:text-gray-200">₹{{ number_format($expense->balance, 2) }}</td>
                                    <td class="px-4 py-2">
                                        @if ($expense->getFirstMedia('bill'))
                                            <a href="{{ $expense->getFirstMediaUrl('bill') }}" target="_blank" class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline">
                                                <x-icon name="paperclip" class="h-3.5 w-3.5" /> Bill
                                            </a>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2 text-gray-500">{{ $expense->remark ?? '—' }}</td>
                                    <td class="whitespace-nowrap px-4 py-2">
                                        <button type="button" @click="editExpense === {{ $expense->id }} ? editExpense = null : editExpense = {{ $expense->id }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                                        <form method="POST" action="{{ route('finance.expenses.destroy', $expense) }}" onsubmit="return confirm('Remove this expense? Balances will be recalculated.')" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Delete</button>
                                        </form>
                                    </td>
                                </tr>
                                <tr x-show="editExpense === {{ $expense->id }}" x-cloak>
                                    <td colspan="12" class="bg-gray-50 px-4 py-3 dark:bg-gray-900">
                                        <form method="POST" action="{{ route('finance.expenses.update', $expense) }}" enctype="multipart/form-data" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                            @csrf
                                            @method('PUT')
                                            <x-text-input type="date" name="expense_date" value="{{ $expense->expense_date->format('Y-m-d') }}" class="text-xs" required />
                                            <x-select-input name="type" class="text-xs">
                                                <option value="debit" @selected($expense->type === 'debit')>Debit</option>
                                                <option value="credit" @selected($expense->type === 'credit')>Credit</option>
                                                <option value="borrow" @selected($expense->type === 'borrow')>Borrow</option>
                                                <option value="lended" @selected($expense->type === 'lended')>Lended</option>
                                            </x-select-input>
                                            <x-text-input type="number" step="0.01" name="amount" value="{{ $expense->amount }}" class="text-xs" required />
                                            <x-select-input name="category" class="text-xs" required>
                                                @foreach ($ledgerCategories as $ledgerCategory)
                                                    <option value="{{ $ledgerCategory->name }}" @selected($expense->category === $ledgerCategory->name)>{{ $ledgerCategory->name }}</option>
                                                @endforeach
                                            </x-select-input>
                                            <x-select-input name="work_order_id" class="text-xs">
                                                <option value="">No work order</option>
                                                @foreach ($workOrders as $wo)<option value="{{ $wo->id }}" @selected($expense->work_order_id === $wo->id)>{{ $wo->work_order_no }}</option>@endforeach
                                            </x-select-input>
                                            <x-text-input name="description" value="{{ $expense->description }}" class="col-span-2 text-xs" />
                                            <x-text-input name="remark" value="{{ $expense->remark }}" class="col-span-2 text-xs" />
                                            <div class="col-span-2 sm:col-span-4">
                                                <x-input-label value="Replace bill (optional)" />
                                                <input type="file" name="bill" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 w-full text-xs">
                                            </div>
                                            <button class="col-span-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 sm:col-span-4">Save</button>
                                        </form>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="12" class="px-4 py-6 text-center text-gray-400">No expenses match this filter.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="px-4">{{ $recentExpenses->appends(request()->query())->links() }}</div>
                @if ($ledgerCategories->isEmpty())
                    <p class="border-t border-gray-100 p-4 text-sm text-gray-400 dark:border-gray-800">No categories yet - add one in the Category List tab first.</p>
                @else
                    <form method="POST" action="{{ route('finance.expenses.store') }}" enctype="multipart/form-data" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-4">
                        @csrf
                        <x-text-input type="date" name="expense_date" value="{{ now()->format('Y-m-d') }}" class="text-sm" required />
                        <x-select-input name="type" class="text-sm">
                            <option value="debit">Debit</option>
                            <option value="credit">Credit</option>
                            <option value="borrow">Borrow</option>
                            <option value="lended">Lended</option>
                        </x-select-input>
                        <x-text-input type="number" step="0.01" name="amount" placeholder="Amount" class="text-sm" required />
                        <x-select-input name="category" class="text-sm" required>
                            @foreach ($ledgerCategories as $ledgerCategory)
                                <option value="{{ $ledgerCategory->name }}">{{ $ledgerCategory->name }}</option>
                            @endforeach
                        </x-select-input>
                        <x-select-input name="work_order_id" class="text-sm">
                            <option value="">Work Order (optional)</option>
                            @foreach ($workOrders as $wo)<option value="{{ $wo->id }}">{{ $wo->work_order_no }}</option>@endforeach
                        </x-select-input>
                        <x-text-input name="description" placeholder="Description" class="col-span-2 text-sm" />
                        <x-text-input name="remark" placeholder="Remark (optional)" class="col-span-2 text-sm" />
                        <div class="col-span-2 sm:col-span-4">
                            <x-input-label value="Bill (image or PDF, optional)" />
                            <input type="file" name="bill" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 w-full text-sm">
                        </div>
                        <x-primary-button class="col-span-2 justify-center sm:col-span-4">Add Expense</x-primary-button>
                    </form>
                @endif
            </x-card>
        </div>

        {{-- ==================== CATEGORY LIST ==================== --}}
        <div x-show="tab === 'categories'" x-cloak>
            <x-card :padded="false">
                <div class="p-4">
                    <h3 class="text-sm font-semibold text-gray-500">Category List</h3>
                    <p class="mt-1 text-xs text-gray-400">Manage the predefined categories selectable when entering Company Ledger and Finance entries (Expenses, Sub Contractor Payments).</p>
                </div>
                <div class="flex flex-wrap gap-2 border-t border-gray-100 p-4 dark:border-gray-800">
                    @forelse ($ledgerCategories as $ledgerCategory)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                            {{ $ledgerCategory->name }}
                            @if (auth()->user()->hasRole('Admin'))
                                <form method="POST" action="{{ route('admin.ledger-categories.destroy', $ledgerCategory) }}" onsubmit="return confirm('Remove the category &quot;{{ $ledgerCategory->name }}&quot;? Existing entries keep their category text.')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-gray-400 hover:text-rose-500" title="Remove category">&times;</button>
                                </form>
                            @endif
                        </span>
                    @empty
                        <p class="text-xs text-gray-400">No categories yet - add one below.</p>
                    @endforelse
                </div>
                @if (auth()->user()->hasRole('Admin'))
                    <form method="POST" action="{{ route('admin.ledger-categories.store') }}" class="flex gap-2 border-t border-gray-100 p-4 dark:border-gray-800">
                        @csrf
                        <x-text-input name="name" placeholder="New category name" class="text-sm" required />
                        <x-primary-button class="whitespace-nowrap">Add Category</x-primary-button>
                    </form>
                @endif
            </x-card>
        </div>
    </div>
</x-app-layout>
