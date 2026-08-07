<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Ticket" :subtitle="$ticket->ticket_no" />
    </x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('tickets.update', $ticket) }}">
            @method('PUT')
            @include('tickets._form')
        </form>
    </x-card>
</x-app-layout>
