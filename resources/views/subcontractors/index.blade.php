<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Subcontractors" subtitle="Verify subcontractor details and assign them Sites and Work Orders." />
    </x-slot>

    <x-card class="mb-4">
        <form method="GET" action="{{ route('subcontractors.index') }}" class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            <x-text-input name="q" placeholder="Search name, email, phone" class="text-sm sm:col-span-2" value="{{ request('q') }}" />
            <x-select-input name="status" class="text-sm" onchange="this.form.submit()">
                <option value="">All Subcontractors</option>
                <option value="verified" @selected(request('status') === 'verified')>Authorised Only</option>
                <option value="unverified" @selected(request('status') === 'unverified')>Not Yet Authorised</option>
            </x-select-input>
            <x-primary-button class="justify-center">Filter</x-primary-button>
        </form>
    </x-card>

    <x-card :padded="false">
        @if ($subcontractors->isEmpty())
            <div class="p-6"><x-empty-state icon="users" title="No subcontractor users found" description="Create a user with the Sub Contractor role from Administration → Users first." /></div>
        @else
            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($subcontractors as $subcontractor)
                        <tr class="cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800/40" onclick="window.location='{{ route('subcontractors.show', $subcontractor) }}'">
                            <td class="px-4 py-3">
                                <p class="font-medium text-gray-800 dark:text-gray-200">{{ $subcontractor->name }}</p>
                                <p class="text-xs text-gray-400">{{ $subcontractor->subcontractorProfile?->company_name ?? $subcontractor->email }}</p>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $subcontractor->phone ?? $subcontractor->subcontractorProfile?->phone ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($subcontractor->subcontractorProfile?->is_verified)
                                    <x-badge color="emerald">Authorised Subcontractor</x-badge>
                                @else
                                    <x-badge color="gray">Not Verified</x-badge>
                                @endif
                            </td>
                            <td class="px-4 py-3"><x-badge :status="$subcontractor->is_active ? 'active' : 'inactive'" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-card>

    <div class="mt-4">{{ $subcontractors->links() }}</div>
</x-app-layout>
