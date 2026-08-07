<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Add Worker" />
    </x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('employees.store') }}">
            @include('employees._form')
        </form>
    </x-card>
</x-app-layout>
