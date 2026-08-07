<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Add Company Record" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('company-records.store') }}">
            @include('company-records._form')
        </form>
    </x-card>
</x-app-layout>
