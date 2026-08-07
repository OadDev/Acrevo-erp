<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Search Results" :subtitle="$query ? 'Results for &quot;'.$query.'&quot;' : 'Type something to search'" />
    </x-slot>

    <x-card :padded="false">
        @if ($results->isEmpty())
            <div class="p-6">
                <x-empty-state icon="search" title="No results found" description="Search across clients, enquiries, work orders, tickets, and employees." />
            </div>
        @else
            <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($results as $result)
                    <li>
                        <a href="{{ $result['url'] }}" class="flex items-center justify-between px-5 py-3 hover:bg-gray-50 dark:hover:bg-gray-800/40">
                            <span class="text-sm text-gray-700 dark:text-gray-300">{{ $result['title'] }}</span>
                            <x-badge color="indigo" :status="$result['type']" />
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</x-app-layout>
