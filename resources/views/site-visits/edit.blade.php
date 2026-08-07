<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Site Visit" />
    </x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('site-visits.update', $siteVisit) }}">
            @method('PUT')
            @include('site-visits._form')
        </form>
    </x-card>
</x-app-layout>
