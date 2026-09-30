<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Tax Invoice" :subtitle="$taxInvoice->tax_invoice_no" />
    </x-slot>

    <form method="POST" action="{{ route('tax-invoices.update', $taxInvoice) }}">
        @csrf
        @method('PUT')
        @include('tax-invoices._form')

        <div class="mt-6 flex justify-end gap-2">
            <x-link-button :href="route('tax-invoices.show', $taxInvoice)" variant="secondary">Cancel</x-link-button>
            <x-primary-button>Save Changes</x-primary-button>
        </div>
    </form>

    @push('scripts')
        @include('tax-invoices._builder-script')
    @endpush
</x-app-layout>
