<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Pending Asset Changes" subtitle="Management-submitted edits waiting for Admin approval." />
    </x-slot>

    <x-card :padded="false">
        @if ($changeRequests->isEmpty())
            <div class="p-6"><x-empty-state icon="clipboard-check" title="Nothing pending" description="Every submitted asset change has been reviewed." /></div>
        @else
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($changeRequests as $changeRequest)
                    <div class="p-4" x-data="{ open: false }">
                        <div class="flex items-center justify-between">
                            <div>
                                <a href="{{ route('assets.show', $changeRequest->asset) }}" class="font-medium text-indigo-600 hover:underline">{{ $changeRequest->asset->asset_code }} — {{ $changeRequest->asset->name }}</a>
                                <p class="text-xs text-gray-400">Requested by {{ $changeRequest->requestedBy?->name }} &middot; {{ $changeRequest->created_at->format('d M Y, h:i A') }}</p>
                            </div>
                            <button type="button" @click="open = !open" class="text-xs font-medium text-indigo-600 hover:underline">Review changes</button>
                        </div>

                        <div x-show="open" x-cloak class="mt-3 overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
                            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                                <thead class="bg-gray-50 dark:bg-gray-800/50">
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                        <th class="px-3 py-2">Field</th>
                                        <th class="px-3 py-2">Old Value</th>
                                        <th class="px-3 py-2">New Value</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($changeRequest->new_values as $field => $newValue)
                                        @php $oldValue = $changeRequest->old_values[$field] ?? null; @endphp
                                        @if ((string) $oldValue !== (string) $newValue)
                                            <tr>
                                                <td class="px-3 py-2 font-medium text-gray-700 dark:text-gray-300">{{ ucwords(str_replace('_', ' ', $field)) }}</td>
                                                <td class="px-3 py-2 text-gray-500">{{ $oldValue ?: '—' }}</td>
                                                <td class="px-3 py-2 text-gray-800 dark:text-gray-200">{{ $newValue ?: '—' }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-3 flex items-center gap-2">
                            <form method="POST" action="{{ route('asset-change-requests.approve', $changeRequest) }}" class="flex items-center gap-2">
                                @csrf
                                <input type="text" name="remarks" placeholder="Approval remarks (optional)" class="rounded-lg border-gray-200 text-xs dark:border-gray-700 dark:bg-gray-800">
                                <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Approve</button>
                            </form>
                            <form method="POST" action="{{ route('asset-change-requests.reject', $changeRequest) }}" class="flex items-center gap-2" onsubmit="return confirm('Reject this change request?')">
                                @csrf
                                <input type="text" name="remarks" placeholder="Rejection remarks (optional)" class="rounded-lg border-gray-200 text-xs dark:border-gray-700 dark:bg-gray-800">
                                <button class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Reject</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $changeRequests->links() }}</div>
</x-app-layout>
