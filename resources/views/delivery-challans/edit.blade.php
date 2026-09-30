<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Delivery Challan" :subtitle="$deliveryChallan->challan_no" />
    </x-slot>

    <form method="POST" action="{{ route('delivery-challans.update', $deliveryChallan) }}">
        @csrf
        @method('PUT')
        @include('delivery-challans._form')

        <div class="mt-6 flex justify-end gap-2">
            <x-link-button :href="route('delivery-challans.show', $deliveryChallan)" variant="secondary">Cancel</x-link-button>
            <x-primary-button>Save Changes</x-primary-button>
        </div>
    </form>

    @push('scripts')
        @include('delivery-challans._builder-script')
    @endpush
</x-app-layout>
