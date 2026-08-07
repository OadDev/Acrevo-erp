<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Schedule Site Visit" />
    </x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('site-visits.store') }}">
            @include('site-visits._form')
        </form>
    </x-card>
</x-app-layout>
