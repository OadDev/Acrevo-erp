<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Client" subtitle="Add a client record independent of an enquiry." />
    </x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('clients.store') }}">
            @include('clients._form')
        </form>
    </x-card>
</x-app-layout>
