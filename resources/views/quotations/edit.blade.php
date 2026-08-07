<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Quotation" :subtitle="$quotation->quotation_no.' (v'.$quotation->version.')'" />
    </x-slot>

    <div
        x-data="quotationBuilder({
            items: {{ $quotation->items->map(fn ($i) => $i->only(['item_type', 'name', 'description', 'unit', 'quantity', 'unit_price', 'discount']))->values()->toJson() }},
            taxPercent: {{ $quotation->tax_percent }},
            discountType: '{{ $quotation->discount_type }}',
            discountValue: {{ $quotation->discount_value }},
        })"
    >
        <form method="POST" action="{{ route('quotations.update', $quotation) }}">
            @csrf
            @method('PUT')

            @include('quotations._builder')

            <div class="mt-6 flex justify-end gap-2">
                <x-link-button :href="route('quotations.show', $quotation)" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </div>

    @push('scripts')
        @include('quotations._builder-script')
    @endpush
</x-app-layout>
