<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Enquiry" subtitle="Every Work Order in Geethan Works starts here." />
    </x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('enquiries.store') }}">
            @include('enquiries._form')
        </form>
    </x-card>
</x-app-layout>
