<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Assign Task" subtitle="One-time task not linked to any work order." />
    </x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('tasks.store') }}">
            @csrf

            <div class="space-y-5">
                <div>
                    <x-input-label for="assigned_to" value="Assign To" />
                    <x-select-input id="assigned_to" name="assigned_to" class="mt-1 block w-full" required>
                        <option value="">Select user</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(old('assigned_to') == $user->id)>{{ $user->name }} @if ($user->designation) ({{ $user->designation }}) @endif</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="title" value="Task Description" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ old('title') }}" placeholder="e.g. Print the plan, fill petrol in the bike, collect document" required />
                </div>

                <div>
                    <x-input-label for="description" value="Additional Details (optional)" />
                    <x-textarea-input id="description" name="description" rows="3" class="mt-1 block w-full">{{ old('description') }}</x-textarea-input>
                </div>

                <div>
                    <x-input-label for="due_date" value="Due Date" />
                    <x-text-input id="due_date" type="date" name="due_date" class="mt-1 block w-full" value="{{ old('due_date', now()->format('Y-m-d')) }}" required />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-link-button :href="route('tasks.index')" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Assign Task</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
