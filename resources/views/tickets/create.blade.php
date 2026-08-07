<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Raise a Ticket" />
    </x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('tickets.store') }}">
            @include('tickets._form')
        </form>
    </x-card>
</x-app-layout>
