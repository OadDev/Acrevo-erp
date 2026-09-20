<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Clients" subtitle="Every client record feeds enquiries, quotations, and work orders.">
            <x-slot name="actions">
                <form action="{{ route('clients.index') }}" class="hidden sm:block">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search clients..." class="rounded-lg border-gray-200 text-sm dark:border-gray-700 dark:bg-gray-800">
                </form>
                <x-link-button :href="route('clients.create')"><x-icon name="plus" class="h-4 w-4" /> New Client</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card :padded="false">
        @if ($clients->isEmpty())
            <div class="p-6">
                <x-empty-state icon="briefcase" title="No clients yet" description="Clients are created automatically when an enquiry converts, or you can add one directly." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Client</th>
                            <th class="px-5 py-3">Phone</th>
                            <th class="px-5 py-3">Assigned Sales</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($clients as $client)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-5 py-3">
                                    <a href="{{ route('clients.show', $client) }}" class="font-medium text-gray-900 hover:text-indigo-600 dark:text-white">{{ $client->name }}</a>
                                    <p class="text-xs text-gray-400">{{ $client->client_code }}</p>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $client->phone }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $client->assignedSales?->name ?? '—' }}</td>
                                <td class="px-5 py-3"><x-badge :status="$client->type" /></td>
                                <td class="px-5 py-3 text-right space-x-3">
                                    @can('clients.manage')
                                        <a href="{{ route('clients.edit', $client) }}" class="text-sm text-indigo-600 hover:underline">Edit</a>
                                        <form method="POST" action="{{ route('clients.destroy', $client) }}" class="inline" onsubmit="return confirm('Remove {{ $client->name }}? This cannot be undone.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-sm text-red-600 hover:underline">Remove</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $clients->links() }}</div>
</x-app-layout>
