<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Quotation" :subtitle="$enquiry->enquiry_no.' — '.($enquiry->client?->name ?? 'Unknown client')" />
    </x-slot>

    <div
        x-data="quotationBuilder({
            items: [{ item_type: 'service', name: '', description: '', unit: 'Nos', quantity: 1, unit_price: 0, discount: 0 }],
            taxPercent: 18,
            discountType: 'flat',
            discountValue: 0,
        })"
    >
        <form method="POST" action="{{ route('quotations.store') }}">
            @csrf
            <input type="hidden" name="enquiry_id" value="{{ $enquiry->id }}">
            <input type="hidden" name="client_id" value="{{ $enquiry->client_id }}">

            @include('quotations._builder')

            <div class="mt-6 flex justify-end gap-2">
                <x-link-button :href="route('enquiries.show', $enquiry)" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Save Quotation</x-primary-button>
            </div>
        </form>
    </div>

    @push('scripts')
        @include('quotations._builder-script')
    @endpush
</x-app-layout>
