<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <x-card class="lg:col-span-2">
        <h3 class="mb-4 text-sm font-semibold text-gray-500">Assigned Executive Teams</h3>
        @forelse ($workOrder->executiveTeams as $assignment)
            <div class="flex items-center justify-between border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                <div>
                    <p class="font-medium text-gray-800 dark:text-gray-200">{{ $assignment->executiveTeam->name }} ({{ $assignment->executiveTeam->team_number }})</p>
                    <p class="text-xs text-gray-400">Leader: {{ $assignment->executiveTeam->teamLeader->name }}</p>
                </div>
                @if (! $assignment->unassigned_at)
                    <x-badge status="active" color="emerald" />
                    @canany(['worker_assignment.manage', 'work_orders.edit'])
                        <form method="POST" action="{{ route('work-orders.unassign-team', [$workOrder, $assignment]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="ml-2 text-xs text-rose-500 hover:underline">Unassign</button>
                        </form>
                    @endcanany
                @else
                    <x-badge status="cancelled" />
                @endif
            </div>
        @empty
            <x-empty-state icon="users-round" title="No executive team assigned" description="HR assigns an executive team to start on-site work." />
        @endforelse
    </x-card>

    @canany(['worker_assignment.manage', 'work_orders.edit'])
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Assign a Team</h3>
            <form method="POST" action="{{ route('work-orders.assign-team', $workOrder) }}" class="space-y-3">
                @csrf
                <x-select-input name="executive_team_id" class="w-full" required>
                    <option value="">Select team</option>
                    @foreach ($availableTeams as $team)
                        <option value="{{ $team->id }}">{{ $team->name }} ({{ $team->team_number }})</option>
                    @endforeach
                </x-select-input>
                <x-primary-button class="w-full justify-center">Assign Team</x-primary-button>
            </form>
        </x-card>
    @endcanany
</div>
