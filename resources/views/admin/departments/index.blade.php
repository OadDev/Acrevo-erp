<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Departments" subtitle="Manage the departments available across Users and Workers." />
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card :padded="false" class="lg:col-span-2" x-data="{ editing: null }">
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <thead class="bg-gray-50 dark:bg-gray-800/50">
                    <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Code</th>
                        <th class="px-4 py-3">In Use</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($departments as $department)
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-800 dark:text-gray-200">{{ $department->name }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $department->code }}</td>
                            <td class="px-4 py-3 text-gray-500">{{ $department->users_count + $department->employees_count }}</td>
                            <td class="px-4 py-3"><x-badge :status="$department->is_active ? 'active' : 'inactive'" /></td>
                            <td class="px-4 py-3 text-right">
                                <button type="button" @click="editing === {{ $department->id }} ? editing = null : editing = {{ $department->id }}" class="text-sm text-indigo-600 hover:underline">Edit</button>
                                <form method="POST" action="{{ route('admin.departments.destroy', $department) }}" class="inline" onsubmit="return confirm('Remove this department?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="ml-3 text-sm text-rose-600 hover:underline">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <tr x-show="editing === {{ $department->id }}" x-cloak>
                            <td colspan="5" class="bg-gray-50 px-4 py-3 dark:bg-gray-900">
                                <form method="POST" action="{{ route('admin.departments.update', $department) }}" class="grid grid-cols-2 gap-2 sm:grid-cols-5">
                                    @csrf
                                    @method('PUT')
                                    <x-text-input name="name" value="{{ $department->name }}" class="text-sm" required />
                                    <x-text-input name="code" value="{{ $department->code }}" class="text-sm" required />
                                    <x-text-input name="description" value="{{ $department->description }}" placeholder="Description (optional)" class="text-sm" />
                                    <label class="flex items-center gap-2 text-sm">
                                        <input type="checkbox" name="is_active" value="1" @checked($department->is_active) class="rounded border-gray-300 text-indigo-600">
                                        Active
                                    </label>
                                    <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-500">Save</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">No departments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Add Department</h3>
            <form method="POST" action="{{ route('admin.departments.store') }}" class="space-y-2">
                @csrf
                <x-text-input name="name" placeholder="Name" class="w-full text-sm" required />
                <x-text-input name="code" placeholder="Code (e.g. OPS)" class="w-full text-sm" required />
                <x-textarea-input name="description" rows="2" class="w-full text-sm" placeholder="Description (optional)"></x-textarea-input>
                <x-primary-button class="w-full justify-center">Add Department</x-primary-button>
            </form>
        </x-card>
    </div>
</x-app-layout>
