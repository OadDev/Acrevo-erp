<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Site Visits" subtitle="Scheduled and completed pre-quotation site inspections." />
    </x-slot>

    <x-card :padded="false">
        @if ($siteVisits->isEmpty())
            <div class="p-6">
                <x-empty-state icon="map-pin" title="No site visits scheduled" description="Schedule a site visit from any enquiry to see it here." />
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Enquiry</th>
                            <th class="px-5 py-3">Scheduled</th>
                            <th class="px-5 py-3">Assigned To</th>
                            <th class="px-5 py-3">Status</th>
                            <th class="px-5 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($siteVisits as $visit)
                            <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/40">
                                <td class="px-5 py-3">
                                    @if ($visit->enquiry)
                                        <a href="{{ route('enquiries.show', $visit->enquiry) }}" class="font-medium text-gray-900 hover:text-indigo-600 dark:text-white">{{ $visit->enquiry->enquiry_no }}</a>
                                        <p class="text-xs text-gray-400">{{ $visit->enquiry->contact_name }}</p>
                                    @else
                                        <span class="font-medium text-gray-400">Deleted enquiry</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $visit->scheduled_at->format('d M Y, h:i A') }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $visit->assignedTo?->name }}</td>
                                <td class="px-5 py-3"><x-badge :status="$visit->status" /></td>
                                <td class="px-5 py-3 text-right">
                                    @if ($visit->status === 'scheduled')
                                        <form method="POST" action="{{ route('site-visits.complete', $visit) }}" class="inline">
                                            @csrf
                                            <button class="text-sm text-emerald-600 hover:underline">Mark Complete</button>
                                        </form>
                                    @endif
                                    @if ($visit->enquiry)
                                        <a href="{{ route('site-visits.edit', $visit) }}" class="ml-2 text-sm text-indigo-600 hover:underline">Edit</a>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $siteVisits->links() }}</div>
</x-app-layout>
