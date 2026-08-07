<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit User" :subtitle="$user->email" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('admin.users.update', $user) }}">
            @csrf
            @method('PUT')
            <div class="space-y-4">
                <div>
                    <x-input-label for="name" value="Full Name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name', $user->name) }}" required />
                </div>
                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" type="email" name="email" class="mt-1 block w-full" value="{{ old('email', $user->email) }}" required />
                </div>
                <div>
                    <x-input-label for="phone" value="Phone" />
                    <x-text-input id="phone" name="phone" class="mt-1 block w-full" value="{{ old('phone', $user->phone) }}" />
                </div>
                <div>
                    <x-input-label for="department_id" value="Department" />
                    <x-select-input id="department_id" name="department_id" class="mt-1 block w-full">
                        <option value="">—</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id', $user->department_id) == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label for="designation" value="Designation" />
                    <x-text-input id="designation" name="designation" class="mt-1 block w-full" value="{{ old('designation', $user->designation) }}" />
                </div>
                <div>
                    <x-input-label for="role" value="Role" />
                    <x-select-input id="role" name="role" class="mt-1 block w-full" required>
                        @foreach ($roles as $role)
                            <option value="{{ $role->name }}" @selected($user->hasRole($role->name))>{{ $role->name }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <label class="flex items-center gap-2 text-sm">
                    <input type="checkbox" name="is_active" value="1" @checked($user->is_active) class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    Active
                </label>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-link-button :href="route('admin.users.index')" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
