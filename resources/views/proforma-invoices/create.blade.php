<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Proforma Invoice" subtitle="Create a proforma invoice for a client." />
    </x-slot>

    <form method="POST" action="{{ route('proforma-invoices.store') }}">
        @csrf
        @include('proforma-invoices._form')

        <div class="mt-6 flex justify-end gap-2">
            <x-link-button :href="route('proforma-invoices.index')" variant="secondary">Cancel</x-link-button>
            <x-primary-button>Save Proforma Invoice</x-primary-button>
        </div>
    </form>

    @push('scripts')
        @include('proforma-invoices._builder-script')
    @endpush
</x-app-layout>
