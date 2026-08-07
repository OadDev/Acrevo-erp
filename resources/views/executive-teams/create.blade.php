<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Executive Team" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('executive-teams.store') }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <x-input-label for="name" value="Team Name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" required />
                </div>
                <div>
                    <x-input-label for="team_leader_id" value="Team Leader" />
                    <x-select-input id="team_leader_id" name="team_leader_id" class="mt-1 block w-full" required>
                        @foreach ($teamLeaders as $leader)
                            <option value="{{ $leader->id }}">{{ $leader->name }}</option>
                        @endforeach
                    </x-select-input>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-link-button :href="route('executive-teams.index')" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Create Team</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
