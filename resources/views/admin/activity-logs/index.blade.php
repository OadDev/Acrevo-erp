<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Activity Logs" subtitle="Every change to critical records, across every department." />
    </x-slot>

    <x-card :padded="false">
        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <th class="px-5 py-3">User</th>
                    <th class="px-5 py-3">Action</th>
                    <th class="px-5 py-3">Subject</th>
                    <th class="px-5 py-3">When</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($activities as $activity)
                    <tr>
                        <td class="px-5 py-3">{{ $activity->causer?->name ?? 'System' }}</td>
                        <td class="px-5 py-3 text-gray-600 dark:text-gray-300">{{ $activity->description }}</td>
                        <td class="px-5 py-3 text-gray-500">{{ class_basename($activity->subject_type ?? '') }} #{{ $activity->subject_id }}</td>
                        <td class="px-5 py-3 text-gray-400">{{ $activity->created_at->diffForHumans() }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-5 py-6 text-center text-gray-400">No activity recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $activities->links() }}</div>
</x-app-layout>
