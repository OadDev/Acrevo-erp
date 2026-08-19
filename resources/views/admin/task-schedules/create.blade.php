<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Calendar Task" subtitle="Define a recurring task for a user or an entire designation." />
    </x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('admin.task-schedules.store') }}" x-data="{ assigneeType: '{{ $prefillRole ? 'role' : 'user' }}', frequency: 'daily' }">
            @csrf

            <div class="space-y-5">
                <div>
                    <x-input-label for="title" value="Task Name" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ old('title') }}" placeholder="e.g. Check enquiry status" required />
                </div>

                <div>
                    <x-input-label for="description" value="Description (optional)" />
                    <x-textarea-input id="description" name="description" rows="2" class="mt-1 block w-full">{{ old('description') }}</x-textarea-input>
                </div>

                <div>
                    <x-input-label value="Assign To" />
                    <div class="mt-2 flex gap-4">
                        <label class="flex items-center gap-1.5 text-sm"><input type="radio" name="assignee_type" value="user" x-model="assigneeType" class="text-indigo-600 focus:ring-indigo-500"> Specific User</label>
                        <label class="flex items-center gap-1.5 text-sm"><input type="radio" name="assignee_type" value="role" x-model="assigneeType" class="text-indigo-600 focus:ring-indigo-500"> Designation (Role)</label>
                    </div>
                </div>

                <div x-show="assigneeType === 'user'">
                    <x-select-input name="assigned_to_user_id" class="w-full">
                        <option value="">Select user</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(old('assigned_to_user_id', $prefillUserId) == $user->id)>{{ $user->name }} @if ($user->designation) ({{ $user->designation }}) @endif</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div x-show="assigneeType === 'role'" x-cloak>
                    <x-select-input name="assignee_role" class="w-full">
                        <option value="">Select designation</option>
                        @foreach ($roles as $roleOption)
                            <option value="{{ $roleOption }}" @selected(old('assignee_role', $prefillRole) === $roleOption)>{{ $roleOption }}</option>
                        @endforeach
                    </x-select-input>
                    <p class="mt-1 text-xs text-gray-400">Generates one task per active user holding this role, each period.</p>
                </div>

                <div>
                    <x-input-label for="frequency" value="Frequency" />
                    <x-select-input id="frequency" name="frequency" x-model="frequency" class="mt-1 block w-full">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                        <option value="yearly">Yearly</option>
                    </x-select-input>
                </div>

                <div x-show="frequency === 'weekly'" x-cloak>
                    <x-input-label for="day_of_week" value="Day of Week" />
                    <x-select-input id="day_of_week" name="day_of_week" class="mt-1 block w-full">
                        @foreach (['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'] as $i => $day)
                            <option value="{{ $i }}" @selected(old('day_of_week') == $i)>{{ $day }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div x-show="frequency === 'monthly' || frequency === 'yearly'" x-cloak>
                    <x-input-label for="day_of_month" value="Day of Month" />
                    <x-text-input id="day_of_month" type="number" min="1" max="31" name="day_of_month" class="mt-1 block w-full" value="{{ old('day_of_month', 1) }}" />
                </div>

                <div x-show="frequency === 'yearly'" x-cloak>
                    <x-input-label for="month_of_year" value="Month" />
                    <x-select-input id="month_of_year" name="month_of_year" class="mt-1 block w-full">
                        @foreach (['January','February','March','April','May','June','July','August','September','October','November','December'] as $i => $month)
                            <option value="{{ $i + 1 }}" @selected(old('month_of_year') == $i + 1)>{{ $month }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="verifier_user_id" value="Verifier" />
                    <x-select-input id="verifier_user_id" name="verifier_user_id" class="mt-1 block w-full" required>
                        <option value="">Select verifier</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}" @selected(old('verifier_user_id') == $user->id)>{{ $user->name }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" checked class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Active — generate task instances on schedule
                </label>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-link-button :href="route('admin.task-schedules.index')" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Create Calendar Task</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
