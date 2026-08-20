<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Candidates / Interviews" subtitle="Interview pipeline and candidate database.">
            <x-slot name="actions">
                <x-link-button :href="route('candidates.create')"><x-icon name="plus" class="h-4 w-4" /> Record Candidate</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('candidates.index') }}" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-text-input name="q" placeholder="Search name, position, phone" class="text-sm sm:col-span-2" value="{{ request('q') }}" />
            <x-select-input name="status" class="text-sm" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                @foreach (['pending' => 'Pending', 'selected' => 'Selected', 'not_selected' => 'Not Selected', 'hired' => 'Hired'] as $value => $label)
                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                @endforeach
            </x-select-input>
            <x-primary-button class="justify-center">Filter</x-primary-button>
        </form>
    </x-card>

    <x-card :padded="false">
        @if ($candidates->isEmpty())
            <div class="p-6"><x-empty-state icon="users" title="No candidates recorded yet" /></div>
        @else
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($candidates as $candidate)
                        <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('candidates.show', $candidate) }}'">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800 dark:text-gray-200">{{ $candidate->name }}</p>
                                <p class="text-xs text-gray-400">{{ $candidate->position_applied }} · {{ $candidate->department?->name }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $candidate->phone }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ optional($candidate->interview_date)->format('d M Y') ?? '—' }}</td>
                            <td class="px-4 py-3"><x-badge :status="$candidate->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    <div class="mt-4">{{ $candidates->links() }}</div>
</x-app-layout>
