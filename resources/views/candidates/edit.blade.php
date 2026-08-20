<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Candidate" :subtitle="$candidate->name" />
    </x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('candidates.update', $candidate) }}" enctype="multipart/form-data">
            @method('PUT')
            @include('candidates._form')
        </form>
    </x-card>
</x-app-layout>
