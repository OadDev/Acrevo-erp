<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Users" subtitle="Every login to Acrevo ERP, with role assignment.">
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
                            <a href="{{ route('admin.users.edit', $user) }}" class="text-sm text-indigo-600 hover:underline">Edit</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-card>

    <div class="mt-4">{{ $users->links() }}</div>
</x-app-layout>
