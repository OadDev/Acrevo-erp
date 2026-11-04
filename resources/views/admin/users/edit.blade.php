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

    @can('permissions.manage')
        <x-card class="mt-6 max-w-4xl">
            <h3 class="mb-1 text-sm font-semibold text-gray-700 dark:text-gray-200">Individual Permissions</h3>
            <p class="mb-4 text-xs text-gray-400">
                Grant this user extra permissions on top of their role, without creating a new role or reassigning them.
                Permissions already granted by their <strong>{{ $user->getRoleNames()->first() ?? 'role' }}</strong> role are shown checked and locked below - untick a role here to remove those instead.
            </p>
            <form method="POST" action="{{ route('admin.users.permissions.update', $user) }}">
                @csrf
                @method('PUT')
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($permissions as $group => $items)
                        <div class="rounded-lg border border-gray-200 p-3 dark:border-gray-800">
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ str_replace('_', ' ', $group) }}</p>
                            <div class="space-y-1.5">
                                @foreach ($items as $permission)
                                    @php($grantedByRole = in_array($permission->name, $rolePermissionNames, true))
                                    <label class="flex items-center gap-2 text-sm {{ $grantedByRole ? 'text-gray-400' : '' }}">
                                        <input
                                            type="checkbox"
                                            name="permissions[]"
                                            value="{{ $permission->name }}"
                                            @checked($grantedByRole || in_array($permission->name, old('permissions', $directPermissionNames), true))
                                            @disabled($grantedByRole)
                                            class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        >
                                        {{ $permission->name }}
                                        @if ($grantedByRole)
                                            <span class="text-gray-400">(via role)</span>
                                        @endif
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-6 flex justify-end">
                    <x-primary-button>Save Individual Permissions</x-primary-button>
                </div>
            </form>
        </x-card>
    @endcan
</x-app-layout>
