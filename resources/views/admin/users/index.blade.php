<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Users" subtitle="Every login to Geethan Works ERP, with role assignment.">
            <x-slot name="actions">
                <x-link-button :href="route('admin.users.create')"><x-icon name="plus" class="h-4 w-4" /> New User</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card :padded="false">
        <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
            <thead class="bg-gray-50 dark:bg-gray-800/50">
                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                    <th class="px-5 py-3">User</th>
                    <th class="px-5 py-3">Role</th>
                    <th class="px-5 py-3">Department</th>
                    <th class="px-5 py-3">Status</th>
                    <th class="px-5 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                @foreach ($users as $user)
                    <tr>
                        <td class="px-5 py-3">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $user->name }}</span>
                            <p class="text-xs text-gray-400">{{ $user->email }}</p>
                        </td>
                        <td class="px-5 py-3"><x-badge color="indigo" :status="$user->roles->first()?->name ?? 'No role'" /></td>
                        <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $user->department?->name ?? '—' }}</td>
                        <td class="px-5 py-3"><x-badge :status="$user->is_active ? 'active' : 'cancelled'" /></td>
                        <td class="px-5 py-3 text-right">
                            <div class="flex items-center justify-end gap-3">
                                <a href="{{ route('admin.users.edit', $user) }}" class="text-sm text-indigo-600 hover:underline">Edit</a>
                                @unless ($user->is(auth()->user()))
                                    @if ($user->is_active)
                                        <form method="POST" action="{{ route('admin.users.deactivate', $user) }}" onsubmit="return confirm('Deactivate {{ $user->name }}?')">
                                            @csrf
                                            <button type="submit" class="text-sm text-amber-600 hover:underline">Deactivate</button>
                                        </form>
                                    @endif
                                    <form method="POST" action="{{ route('admin.users.destroy', $user) }}" onsubmit="return confirm('Permanently delete {{ $user->name }}? This cannot be undone.')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-sm text-red-600 hover:underline">Delete</button>
                                    </form>
                                @endunless
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $users->links() }}</div>
</x-app-layout>
