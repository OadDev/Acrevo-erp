<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Delivery Challan" subtitle="Record a goods delivery for a client." />
    </x-slot>

    <form method="POST" action="{{ route('delivery-challans.store') }}">
        @csrf
        @include('delivery-challans._form')

        <div class="mt-6 flex justify-end gap-2">
            <x-link-button :href="route('delivery-challans.index')" variant="secondary">Cancel</x-link-button>
            <x-primary-button>Save Delivery Challan</x-primary-button>
        </div>
    </form>

    @push('scripts')
        @include('delivery-challans._builder-script')
    @endpush
</x-app-layout>
