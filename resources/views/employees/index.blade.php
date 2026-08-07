<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Workers" subtitle="Current and relieved workers across all executive teams.">
            <x-slot name="actions">
                <x-select-input name="status" class="text-sm" onchange="location.href='{{ route('employees.index') }}?status='+this.value">
                    <option value="active" @selected(request('status', 'active') === 'active')>Current Workers</option>
                    <option value="relieved" @selected(request('status') === 'relieved')>Relieved Workers</option>
                    <option value="all" @selected(request('status') === 'all')>All</option>
                </x-select-input>
                @can('employees.create')
                    <x-link-button :href="route('employees.create')"><x-icon name="plus" class="h-4 w-4" /> Add Worker</x-link-button>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card :padded="false">
        @if ($employees->isEmpty())
            <div class="p-6"><x-empty-state icon="users" title="No workers found" /></div>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-100 dark:divide-gray-800">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                            <th class="px-5 py-3">Worker</th>
                            <th class="px-5 py-3">Designation</th>
                            <th class="px-5 py-3">Department</th>
                            <th class="px-5 py-3">Type</th>
                            <th class="px-5 py-3">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($employees as $employee)
                            <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('employees.show', $employee) }}'">
                                <td class="px-5 py-3">
                                    <span class="font-medium text-gray-900 dark:text-white">{{ $employee->name }}</span>
                                    <p class="text-xs text-gray-400">{{ $employee->employee_code }}</p>
                                </td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $employee->designation }}</td>
                                <td class="px-5 py-3 text-sm text-gray-600 dark:text-gray-300">{{ $employee->department?->name ?? '—' }}</td>
                                <td class="px-5 py-3"><x-badge color="indigo" :status="$employee->employment_type" /></td>
                                <td class="px-5 py-3"><x-badge :status="$employee->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-card>

    <div class="mt-4">{{ $employees->links() }}</div>
</x-app-layout>
