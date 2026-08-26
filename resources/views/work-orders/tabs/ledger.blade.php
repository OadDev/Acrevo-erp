@php
    $filtered = $workOrder->ledgers
        ->when(request('ledger_from'), fn ($c) => $c->filter(fn ($e) => $e->entry_date->format('Y-m-d') >= request('ledger_from')))
        ->when(request('ledger_to'), fn ($c) => $c->filter(fn ($e) => $e->entry_date->format('Y-m-d') <= request('ledger_to')))
        ->when(request('ledger_category'), fn ($c) => $c->filter(fn ($e) => $e->category === request('ledger_category')))
        ->when(request('ledger_type'), fn ($c) => $c->filter(fn ($e) => $e->type === request('ledger_type')));
    $categories = $workOrder->ledgers->pluck('category')->filter()->unique()->sort();
    $ledgerCategories = \App\Models\LedgerCategory::orderBy('name')->get();
    $currentBalance = $workOrder->ledgers->last()?->balance ?? 0;
@endphp

@if (auth()->user()->hasRole('Admin'))
    <x-card class="mb-6" :padded="false">
        <div class="p-4">
            <h3 class="text-sm font-semibold text-gray-500">Ledger Categories</h3>
            <p class="mt-1 text-xs text-gray-400">Manage the predefined categories selectable when entering Site Ledger entries.</p>
        </div>
        <div class="flex flex-wrap gap-2 border-t border-gray-100 p-4 dark:border-gray-800">
            @forelse ($ledgerCategories as $ledgerCategory)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-100 px-3 py-1 text-xs font-medium text-gray-600 dark:bg-gray-800 dark:text-gray-300">
                    {{ $ledgerCategory->name }}
                    <form method="POST" action="{{ route('admin.ledger-categories.destroy', $ledgerCategory) }}" onsubmit="return confirm('Remove the category &quot;{{ $ledgerCategory->name }}&quot;? Existing ledger entries keep their category text.')">
                        @csrf
                        @method('DELETE')
                        <button class="text-gray-400 hover:text-rose-500" title="Remove category">&times;</button>
                    </form>
                </span>
            @empty
                <p class="text-xs text-gray-400">No categories yet - add one below.</p>
            @endforelse
        </div>
        <form method="POST" action="{{ route('admin.ledger-categories.store') }}" class="flex gap-2 border-t border-gray-100 p-4 dark:border-gray-800">
            @csrf
            <x-text-input name="name" placeholder="New category name" class="text-sm" required />
            <x-primary-button class="whitespace-nowrap">Add Category</x-primary-button>
        </form>
    </x-card>
@endif

<x-card class="mb-6">
    <h3 class="text-sm font-semibold text-gray-500">Current Balance</h3>
    <p class="text-2xl font-semibold text-gray-900 dark:text-white">₹{{ number_format($currentBalance, 2) }}</p>
</x-card>

<x-card :padded="false" x-data="{ editLedger: null }">
    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
        <h3 class="text-sm font-semibold text-gray-500">Site Ledger</h3>
        <a href="{{ route('work-orders.ledger.export', array_filter([
            'workOrder' => $workOrder,
            'from' => request('ledger_from'),
            'to' => request('ledger_to'),
            'category' => request('ledger_category'),
            'type' => request('ledger_type'),
        ])) }}" class="inline-flex items-center gap-1.5 text-sm font-medium text-indigo-600 hover:text-indigo-500">
            <x-icon name="download" class="h-4 w-4" /> Download CSV
        </a>
    </div>

    <form method="GET" action="{{ route('work-orders.show', $workOrder) }}#ledger" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-5">
        <input type="hidden" name="tab" value="ledger">
        <x-text-input type="date" name="ledger_from" value="{{ request('ledger_from') }}" placeholder="From" class="text-sm" />
        <x-text-input type="date" name="ledger_to" value="{{ request('ledger_to') }}" placeholder="To" class="text-sm" />
        <x-select-input name="ledger_category" class="text-sm">
            <option value="">All Categories</option>
            @foreach ($categories as $category)
                <option value="{{ $category }}" @selected(request('ledger_category') === $category)>{{ $category }}</option>
            @endforeach
        </x-select-input>
        <x-select-input name="ledger_type" class="text-sm">
            <option value="">All Types</option>
            <option value="credit" @selected(request('ledger_type') === 'credit')>Credit</option>
            <option value="debit" @selected(request('ledger_type') === 'debit')>Debit</option>
            <option value="borrow" @selected(request('ledger_type') === 'borrow')>Borrow</option>
            <option value="lended" @selected(request('ledger_type') === 'lended')>Lended</option>
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
                    <th class="px-4 py-2 text-right">Borrow</th>
                    <th class="px-4 py-2 text-right">Credit</th>
                    <th class="px-4 py-2 text-right">Debit</th>
                    <th class="px-4 py-2 text-right">Lended</th>
                    <th class="px-4 py-2 text-right">Balance</th>
                    <th class="px-4 py-2">Bill</th>
                    <th class="px-4 py-2">Remark</th>
                    @if (auth()->user()->hasRole('Admin'))
                        <th class="px-4 py-2">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($filtered as $entry)
                    <tr>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->entry_date->format('d M Y') }}</td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->category ?? '—' }}</td>
                        <td class="px-4 py-2">{{ $entry->description }}</td>
                        <td class="px-4 py-2 text-right text-blue-600">{{ $entry->type === 'borrow' ? '₹'.number_format($entry->amount, 2) : '—' }}</td>
                        <td class="px-4 py-2 text-right text-emerald-600">{{ $entry->type === 'credit' ? '₹'.number_format($entry->amount, 2) : '—' }}</td>
                        <td class="px-4 py-2 text-right text-rose-600">{{ $entry->type === 'debit' ? '₹'.number_format($entry->amount, 2) : '—' }}</td>
                        <td class="px-4 py-2 text-right text-amber-600">{{ $entry->type === 'lended' ? '₹'.number_format($entry->amount, 2) : '—' }}</td>
                        <td class="px-4 py-2 text-right font-medium text-gray-800 dark:text-gray-200">₹{{ number_format($entry->balance, 2) }}</td>
                        <td class="px-4 py-2">
                            @if ($entry->getFirstMedia('bill'))
                                <a href="{{ $entry->getFirstMediaUrl('bill') }}" target="_blank" class="inline-flex items-center gap-1 text-xs text-indigo-600 hover:underline">
                                    <x-icon name="paperclip" class="h-3.5 w-3.5" /> Bill
                                </a>
                            @endif
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->remark ?? '—' }}</td>
                        @if (auth()->user()->hasRole('Admin'))
                            <td class="whitespace-nowrap px-4 py-2">
                                <button type="button" @click="editLedger === {{ $entry->id }} ? editLedger = null : editLedger = {{ $entry->id }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                                <form method="POST" action="{{ route('work-orders.ledger.destroy', [$workOrder, $entry]) }}" onsubmit="return confirm('Remove this ledger entry? Balances will be recalculated.')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                    @if (auth()->user()->hasRole('Admin'))
                        <tr x-show="editLedger === {{ $entry->id }}" x-cloak>
                            <td colspan="11" class="bg-gray-50 px-4 py-3 dark:bg-gray-900">
                                <form method="POST" action="{{ route('work-orders.ledger.update', [$workOrder, $entry]) }}" enctype="multipart/form-data" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    @csrf
                                    @method('PUT')
                                    <x-text-input type="date" name="entry_date" value="{{ $entry->entry_date->format('Y-m-d') }}" class="text-xs" required />
                                    <x-select-input name="type" class="text-xs">
                                        <option value="debit" @selected($entry->type === 'debit')>Debit</option>
                                        <option value="credit" @selected($entry->type === 'credit')>Credit</option>
                                        <option value="borrow" @selected($entry->type === 'borrow')>Borrow</option>
                                        <option value="lended" @selected($entry->type === 'lended')>Lended</option>
                                    </x-select-input>
                                    <x-text-input type="number" step="0.01" name="amount" value="{{ $entry->amount }}" class="text-xs" required />
                                    <x-select-input name="category" class="text-xs">
                                        <option value="">No category</option>
                                        @foreach ($ledgerCategories as $ledgerCategory)
                                            <option value="{{ $ledgerCategory->name }}" @selected($entry->category === $ledgerCategory->name)>{{ $ledgerCategory->name }}</option>
                                        @endforeach
                                    </x-select-input>
                                    <x-text-input name="description" value="{{ $entry->description }}" class="col-span-2 text-xs" />
                                    <x-text-input name="remark" value="{{ $entry->remark }}" class="col-span-2 text-xs" />
                                    <div class="col-span-2 sm:col-span-4">
                                        <x-input-label value="Replace bill (optional)" />
                                        <input type="file" name="bill" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 w-full text-xs">
                                    </div>
                                    <button class="col-span-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 sm:col-span-4">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="11" class="px-4 py-6 text-center text-gray-400">No ledger entries match this filter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @can('site_records.manage')
        <form method="POST" action="{{ route('work-orders.ledger.store', $workOrder) }}" enctype="multipart/form-data" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-4">
            @csrf
            <x-select-input name="type" class="text-sm">
                <option value="debit">Debit</option>
                <option value="credit">Credit</option>
                <option value="borrow">Borrow</option>
                <option value="lended">Lended</option>
            </x-select-input>
            <x-text-input type="number" step="0.01" name="amount" placeholder="Amount" class="text-sm" required />
            <x-select-input name="category" class="text-sm">
                <option value="">No category</option>
                @foreach ($ledgerCategories as $ledgerCategory)
                    <option value="{{ $ledgerCategory->name }}">{{ $ledgerCategory->name }}</option>
                @endforeach
            </x-select-input>
            <x-text-input name="description" placeholder="Description" class="text-sm" />
            <x-text-input name="remark" placeholder="Remark (optional)" class="col-span-2 text-sm sm:col-span-4" />
            <div class="col-span-2 sm:col-span-4">
                <x-input-label value="Bill (image or PDF, optional)" />
                <input type="file" name="bill" accept=".jpg,.jpeg,.png,.pdf" class="mt-1 w-full text-sm">
            </div>
            <x-primary-button class="col-span-2 justify-center sm:col-span-4">Add Ledger Entry</x-primary-button>
        </form>
    @endcan
</x-card>
