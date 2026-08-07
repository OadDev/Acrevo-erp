<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Enquiry" :subtitle="$enquiry->enquiry_no" />
    </x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('enquiries.update', $enquiry) }}">
            @method('PUT')
            @include('enquiries._form')
        </form>
    </x-card>
</x-app-layout>
