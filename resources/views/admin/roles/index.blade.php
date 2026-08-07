<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Roles &amp; Permissions" subtitle="Admin can create unlimited roles with granular permissions.">
            <x-slot name="actions">
                <x-link-button :href="route('admin.roles.create')"><x-icon name="plus" class="h-4 w-4" /> New Role</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($roles as $role)
            <x-card>
                <div class="flex items-center justify-between">
                    <p class="font-medium text-gray-900 dark:text-white">{{ $role->name }}</p>
                    @if ($role->name !== 'Admin')
                        <form method="POST" action="{{ route('admin.roles.destroy', $role) }}" onsubmit="return confirm('Delete this role?')">
                            @csrf
                            @method('DELETE')
                            <button class="text-gray-400 hover:text-rose-500"><x-icon name="trash" class="h-4 w-4" /></button>
                        </form>
                    @endif
                </div>
                <p class="mt-1 text-xs text-gray-400">{{ $role->permissions_count }} permission(s) · {{ $role->users_count }} user(s)</p>
                <x-link-button :href="route('admin.roles.edit', $role)" variant="secondary" class="mt-4 w-full justify-center">Edit Permissions</x-link-button>
            </x-card>
        @endforeach
    </div>
</x-app-layout>
