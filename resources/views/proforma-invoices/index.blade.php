<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Proforma Invoices" subtitle="Create, view, and convert proforma invoices to tax invoices.">
            <x-slot name="actions">
                @can('proforma_invoices.create')
                    <x-link-button :href="route('proforma-invoices.create')">New Proforma Invoice</x-link-button>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('proforma-invoices.index') }}" class="flex flex-wrap items-center gap-2">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search by number or client..." class="w-full max-w-xs rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">
            <select name="company_id" class="rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">
                <option value="">All Companies</option>
                @foreach ($companies as $company)
                    <option value="{{ $company->id }}" @selected(request('company_id') == $company->id)>{{ $company->name }}</option>
                @endforeach
            </select>
            <select name="status" class="rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">
                <option value="">All Statuses</option>
                @foreach (\App\Models\ProformaInvoice::STATUSES as $status)
                    <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                @endforeach
            </select>
            <x-primary-button class="justify-center">Filter</x-primary-button>
            @if (request()->hasAny(['q', 'company_id', 'status']))
                <x-link-button :href="route('proforma-invoices.index')" variant="secondary">Clear</x-link-button>
            @endif
        </form>
    </x-card>

    <x-card :padded="false">
        @if ($proformaInvoices->isEmpty())
            <div class="p-6"><x-empty-state icon="file-text" title="No proforma invoices found" description="Adjust your search/filters or create a new one." /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-4 py-3">S.No</th>
                            <th class="px-4 py-3">No.</th>
                            <th class="px-4 py-3">Company</th>
                            <th class="px-4 py-3">Client</th>
                            <th class="px-4 py-3">Date</th>
                            <th class="px-4 py-3 text-right">Total</th>
                            <th class="px-4 py-3">Status</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($proformaInvoices as $proformaInvoice)
                            <tr>
                                <td class="px-4 py-3 text-gray-500">{{ $proformaInvoices->firstItem() + $loop->index }}</td>
                                <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">
                                    <a href="{{ route('proforma-invoices.show', $proformaInvoice) }}" class="hover:underline">{{ $proformaInvoice->proforma_no }}</a>
                                </td>
                                <td class="px-4 py-3 text-gray-500">{{ $proformaInvoice->company?->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $proformaInvoice->client?->name }}</td>
                                <td class="px-4 py-3 text-gray-500">{{ $proformaInvoice->document_date->format('d M Y') }}</td>
                                <td class="px-4 py-3 text-right text-gray-500">{{ $proformaInvoice->company?->currencySymbol() }} {{ number_format($proformaInvoice->total_amount, 2) }}</td>
                                <td class="px-4 py-3"><x-badge :status="$proformaInvoice->status" /></td>
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <a href="{{ route('proforma-invoices.show', $proformaInvoice) }}" class="text-xs font-medium text-indigo-600 hover:underline">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $proformaInvoices->links() }}</div>
</x-app-layout>
