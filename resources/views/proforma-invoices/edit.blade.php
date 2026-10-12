<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Proforma Invoice" :subtitle="$proformaInvoice->proforma_no" />
    </x-slot>

    <form method="POST" action="{{ route('proforma-invoices.update', $proformaInvoice) }}">
        @csrf
        @method('PUT')
        @include('proforma-invoices._form')

        <div class="mt-6 flex justify-end gap-2">
            <x-link-button :href="route('proforma-invoices.show', $proformaInvoice)" variant="secondary">Cancel</x-link-button>
            <x-primary-button>Save Changes</x-primary-button>
        </div>
    </form>

    @push('scripts')
        @include('proforma-invoices._builder-script')
    @endpush
</x-app-layout>
