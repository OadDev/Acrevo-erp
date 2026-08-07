<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$executiveTeam->name" :subtitle="$executiveTeam->team_number.' — Led by '.$executiveTeam->teamLeader->name">
            <x-slot name="actions">
                @can('executive_teams.manage')
                    <x-link-button :href="route('executive-teams.edit', $executiveTeam)" variant="secondary">Edit</x-link-button>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card class="lg:col-span-2">
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Team Members</h3>
            @forelse ($executiveTeam->members as $member)
                <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                    <div>
                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ $member->employee->name }}</p>
                        <p class="text-xs text-gray-400">{{ $member->role_in_team ?: $member->employee->designation }}</p>
                    </div>
                    @can('executive_teams.manage')
                        <form method="POST" action="{{ route('executive-teams.members.destroy', [$executiveTeam, $member]) }}">
                            @csrf
                            @method('DELETE')
                            <button class="text-xs text-rose-500 hover:underline">Remove</button>
                        </form>
                    @endcan
                </div>
            @empty
                <p class="text-sm text-gray-400">No members assigned yet.</p>
            @endforelse

            <h3 class="mb-4 mt-6 text-sm font-semibold text-gray-500">Work Order Assignments</h3>
            @forelse ($executiveTeam->workOrderAssignments as $assignment)
                <a href="{{ route('work-orders.show', $assignment->workOrder) }}" class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                    <span class="text-gray-700 dark:text-gray-300">{{ $assignment->workOrder->work_order_no }} — {{ $assignment->workOrder->title }}</span>
                    <x-badge :status="$assignment->unassigned_at ? 'cancelled' : 'active'" />
                </a>
            @empty
                <p class="text-sm text-gray-400">Not currently assigned to any work order.</p>
            @endforelse
        </x-card>

        @can('executive_teams.manage')
            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Assign a Worker</h3>
                <form method="POST" action="{{ route('executive-teams.members.store', $executiveTeam) }}" class="space-y-3">
                    @csrf
                    <x-select-input name="employee_id" class="w-full" required>
                        @foreach ($availableEmployees as $employee)
                            <option value="{{ $employee->id }}">{{ $employee->name }}</option>
                        @endforeach
                    </x-select-input>
                    <x-text-input name="role_in_team" placeholder="Role in team (optional)" class="w-full" />
                    <x-primary-button class="w-full justify-center">Add to Team</x-primary-button>
                </form>
            </x-card>
        @endcan
    </div>
</x-app-layout>
