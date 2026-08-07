<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Enquiries" subtitle="The starting point of every Work Order.">
            <x-slot name="actions">
                <form action="{{ route('enquiries.index') }}" class="flex gap-2">
                    <x-select-input name="status" class="text-sm" onchange="this.form.submit()">
                        <option value="">All Statuses</option>
                        @foreach (['new', 'contacted', 'site_visit_scheduled', 'quoted', 'converted', 'lost'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ Str::title(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </x-select-input>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search..." class="rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">
                </form>
                @can('enquiries.create')
                    <x-link-button :href="route('enquiries.create')"><x-icon name="plus" class="h-4 w-4" /> New Enquiry</x-link-button>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card :padded="false">
        @if ($enquiries->isEmpty())
            <div class="p-6">
                <x-empty-state icon="inbox" title="No enquiries found" description="New enquiries from clients start the entire OrbitX workflow." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Enquiry</th>
                            <th class="px-5 py-3">Contact</th>
                            <th class="px-5 py-3">Source</th>
                            <th class="px-5 py-3">Assigned To</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($enquiries as $enquiry)
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('enquiries.show', $enquiry) }}'">
                                <td class="px-5 py-3">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $enquiry->enquiry_no }}</span>
                                    <p class="text-xs text-gray-400">{{ $enquiry->service_type }}</p>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">
                                    {{ $enquiry->contact_name }}
                                    <p class="text-xs text-gray-400">{{ $enquiry->contact_phone }}</p>
                                </td>
                                <td class="px-5 py-3"><x-badge color="indigo" :status="$enquiry->source" /></td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $enquiry->assignedTo?->name ?? '—' }}</td>
                                <td class="px-5 py-3"><x-badge :status="$enquiry->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $enquiries->links() }}</div>
</x-app-layout>
