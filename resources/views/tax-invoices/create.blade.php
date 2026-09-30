<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Tax Invoice" subtitle="Create a tax invoice for a client." />
    </x-slot>

    <form method="POST" action="{{ route('tax-invoices.store') }}">
        @csrf
        @include('tax-invoices._form')

        <div class="mt-6 flex justify-end gap-2">
            <x-link-button :href="route('tax-invoices.index')" variant="secondary">Cancel</x-link-button>
            <x-primary-button>Save Tax Invoice</x-primary-button>
        </div>
    </form>

    @push('scripts')
        @include('tax-invoices._builder-script')
    @endpush
</x-app-layout>
