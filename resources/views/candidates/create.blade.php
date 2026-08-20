<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Record Candidate" />
    </x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('candidates.store') }}" enctype="multipart/form-data">
            @include('candidates._form')
        </form>
    </x-card>
</x-app-layout>
