<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Sites" subtitle="Every client site, with the work orders that belong to it.">
            <x-slot name="actions">
                <form method="GET" class="flex items-center gap-2">
                    <x-text-input name="q" placeholder="Search site, client, address..." class="text-sm" value="{{ request('q') }}" />
                </form>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card :padded="false">
        @if ($sites->isEmpty())
            <div class="p-6">
                <x-empty-state icon="map-pin" title="No sites yet" description="Sites are created automatically when a quotation is approved." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Site ID</th>
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Address</th>
                            <th class="px-5 py-3">Work Orders</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($sites as $site)
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('sites.show', $site) }}'">
                                <td class="px-5 py-3">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $site->site_no }}</span>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $site->client->name }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ collect([$site->address, $site->city, $site->state])->filter()->join(', ') ?: '—' }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $site->work_orders_count }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $sites->links() }}</div>
</x-app-layout>
