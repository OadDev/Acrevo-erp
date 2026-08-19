@php
    $canEditSummary = auth()->user()->hasAnyRole(['Admin', 'Sales', 'HR', 'Executive Team Leader']);
@endphp

<x-card :padded="false" x-data="{ editSummary: null }">
    <div class="flex items-center justify-between p-4">
        <h3 class="text-sm font-semibold text-gray-500">Monthly Summary</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
            <thead>
                <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                    <th class="px-4 py-2">Date</th>
                    <th class="px-4 py-2">Work Done/Not</th>
                    <th class="px-4 py-2">Responsibility — Client/Company</th>
                    <th class="px-4 py-2">Work Detail/Reason</th>
                    <th class="px-4 py-2 text-right">No of Days in Client Bear</th>
                    <th class="px-4 py-2 text-right">No of Remaining Construction Days</th>
                    @if ($canEditSummary)
                        <th class="px-4 py-2">Actions</th>
                    @endif
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($workOrder->summaries as $entry)
                    <tr>
                        <td class="px-4 py-2 text-gray-500">{{ $entry->entry_date->format('d M Y') }}</td>
                        <td class="px-4 py-2">
                            <span class="{{ $entry->status === 'done' ? 'text-emerald-600' : 'text-gray-500' }}">{{ $entry->status === 'done' ? 'Done' : 'No' }}</span>
                        </td>
                        <td class="px-4 py-2 text-gray-500">{{ ucfirst($entry->responsibility) }}</td>
                        <td class="px-4 py-2">{{ $entry->work_detail ?? '—' }}</td>
                        <td class="px-4 py-2 text-right text-gray-500">{{ $entry->client_bear_days ?? '—' }}</td>
                        <td class="px-4 py-2 text-right text-gray-500">{{ $entry->remaining_construction_days ?? '—' }}</td>
                        @if ($canEditSummary)
                            <td class="whitespace-nowrap px-4 py-2">
                                <button type="button" @click="editSummary === {{ $entry->id }} ? editSummary = null : editSummary = {{ $entry->id }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                                <form method="POST" action="{{ route('work-orders.summary.destroy', [$workOrder, $entry]) }}" onsubmit="return confirm('Remove this summary entry?')" class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ml-2 text-xs font-medium text-rose-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                    @if ($canEditSummary)
                        <tr x-show="editSummary === {{ $entry->id }}" x-cloak>
                            <td colspan="7" class="bg-gray-50 px-4 py-3 dark:bg-gray-900">
                                <form method="POST" action="{{ route('work-orders.summary.update', [$workOrder, $entry]) }}" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                                    @csrf
                                    @method('PUT')
                                    <x-text-input type="date" name="entry_date" value="{{ $entry->entry_date->format('Y-m-d') }}" class="text-xs" required />
                                    <x-select-input name="status" class="text-xs">
                                        <option value="done" @selected($entry->status === 'done')>Done</option>
                                        <option value="not_done" @selected($entry->status === 'not_done')>No</option>
                                    </x-select-input>
                                    <x-select-input name="responsibility" class="text-xs">
                                        <option value="client" @selected($entry->responsibility === 'client')>Client</option>
                                        <option value="company" @selected($entry->responsibility === 'company')>Company</option>
                                    </x-select-input>
                                    <x-text-input name="work_detail" value="{{ $entry->work_detail }}" placeholder="Work detail/reason" class="text-xs" />
                                    <x-text-input type="number" min="0" name="client_bear_days" value="{{ $entry->client_bear_days }}" placeholder="Days in client bear" class="text-xs" />
                                    <x-text-input type="number" min="0" name="remaining_construction_days" value="{{ $entry->remaining_construction_days }}" placeholder="Remaining construction days" class="text-xs" />
                                    <button class="col-span-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500 sm:col-span-4">Save</button>
                                </form>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No summary entries yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($canEditSummary)
        <form method="POST" action="{{ route('work-orders.summary.store', $workOrder) }}" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-4">
            @csrf
            <x-text-input type="date" name="entry_date" value="{{ now()->format('Y-m-d') }}" class="text-sm" required />
            <x-select-input name="status" class="text-sm">
                <option value="done">Done</option>
                <option value="not_done">No</option>
            </x-select-input>
            <x-select-input name="responsibility" class="text-sm">
                <option value="client">Client</option>
                <option value="company">Company</option>
            </x-select-input>
            <x-text-input name="work_detail" placeholder="Work detail/reason" class="text-sm" />
            <x-text-input type="number" min="0" name="client_bear_days" placeholder="Days in client bear" class="text-sm" />
            <x-text-input type="number" min="0" name="remaining_construction_days" placeholder="Remaining construction days" class="text-sm" />
            <x-primary-button class="col-span-2 justify-center sm:col-span-4">Add Summary Entry</x-primary-button>
        </form>
    @endif
</x-card>
