<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Role" />
    </x-slot>

    <form method="POST" action="{{ route('admin.roles.store') }}">
        @csrf
        <x-card class="mb-6 max-w-xl">
            <x-input-label for="name" value="Role Name" />
            <x-text-input id="name" name="name" class="mt-1 block w-full" required />
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Permissions</h3>
            @include('admin.roles._permissions')
        </x-card>

        <div class="mt-6 flex justify-end gap-2">
            <x-link-button :href="route('admin.roles.index')" variant="secondary">Cancel</x-link-button>
            <x-primary-button>Create Role</x-primary-button>
        </div>
    </form>
</x-app-layout>
