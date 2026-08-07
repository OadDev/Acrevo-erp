<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Executive Teams" subtitle="Field crews assigned to on-site work orders.">
            <x-slot name="actions">
                @can('executive_teams.manage')
                    <x-link-button :href="route('executive-teams.create')"><x-icon name="plus" class="h-4 w-4" /> New Team</x-link-button>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($teams as $team)
            <a href="{{ route('executive-teams.show', $team) }}">
                <x-card class="h-full transition hover:border-indigo-300">
                    <div class="flex items-center justify-between">
                        <p class="font-medium text-gray-900 dark:text-white">{{ $team->name }}</p>
                        <x-badge :status="$team->is_active ? 'active' : 'cancelled'" />
                    </div>
                    <p class="mt-1 text-xs text-gray-400">{{ $team->team_number }}</p>
                    <p class="mt-3 text-sm text-gray-600 dark:text-gray-300">Leader: {{ $team->teamLeader->name }}</p>
                    <p class="text-sm text-gray-500">{{ $team->members->count() }} member(s)</p>
                </x-card>
            </a>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <x-empty-state icon="users-round" title="No executive teams yet" description="HR creates teams here and assigns them to work orders." />
            </div>
        @endforelse
    </div>

    <div class="mt-4">{{ $teams->links() }}</div>
</x-app-layout>
