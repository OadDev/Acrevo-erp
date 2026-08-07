<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Client" :subtitle="$client->client_code" />
    </x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('clients.update', $client) }}">
            @method('PUT')
            @include('clients._form')
        </form>
    </x-card>
</x-app-layout>
