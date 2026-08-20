<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Company Record" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('company-records.update', $record) }}" enctype="multipart/form-data">
            @method('PUT')
            @include('company-records._form')
        </form>
    </x-card>
</x-app-layout>
