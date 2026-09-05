<?php $isAdmin = auth()->user()->hasRole('Admin'); ?>
<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="'Edit ' . $asset->asset_code" :subtitle="$isAdmin ? 'Changes apply immediately.' : 'Changes will be submitted for Admin approval and won\'t take effect until then.'" />
    </x-slot>

    @unless ($isAdmin)
        <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
            Your changes to the fields below will be sent to an Admin for approval. The asset's current details stay active until approved.
        </div>
    @endunless

    <form method="POST" action="{{ $isAdmin ? route('assets.update', $asset) : route('assets.request-update', $asset) }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Asset Details</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Equipment / Tool Name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name', $asset->name) }}" required />
                </div>
                <div>
                    <x-input-label for="category" value="Category" />
                    <x-text-input id="category" name="category" class="mt-1 block w-full" value="{{ old('category', $asset->category) }}" />
                </div>
                <div>
                    <x-input-label for="brand" value="Brand" />
                    <x-text-input id="brand" name="brand" class="mt-1 block w-full" value="{{ old('brand', $asset->brand) }}" />
                </div>
                <div>
                    <x-input-label for="model" value="Model" />
                    <x-text-input id="model" name="model" class="mt-1 block w-full" value="{{ old('model', $asset->model) }}" />
                </div>
                <div>
                    <x-input-label for="serial_number" value="Serial Number" />
                    <x-text-input id="serial_number" name="serial_number" class="mt-1 block w-full" value="{{ old('serial_number', $asset->serial_number) }}" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="remarks" value="Remarks" />
                    <x-textarea-input id="remarks" name="remarks" rows="2" class="mt-1 block w-full">{{ old('remarks', $asset->remarks) }}</x-textarea-input>
                </div>
            </div>
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Purchase Details</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="purchase_date" value="Purchase Date" />
                    <x-text-input id="purchase_date" type="date" name="purchase_date" class="mt-1 block w-full" value="{{ old('purchase_date', optional($asset->purchase_date)->format('Y-m-d')) }}" />
                </div>
                <div>
                    <x-input-label for="purchase_cost" value="Purchase Cost" />
                    <x-text-input id="purchase_cost" type="number" step="0.01" min="0" name="purchase_cost" class="mt-1 block w-full" value="{{ old('purchase_cost', $asset->purchase_cost) }}" />
                </div>
                <div>
                    <x-input-label for="supplier" value="Supplier" />
                    <x-text-input id="supplier" name="supplier" class="mt-1 block w-full" value="{{ old('supplier', $asset->supplier) }}" />
                </div>
                <div>
                    <x-input-label for="invoice_number" value="Invoice Number" />
                    <x-text-input id="invoice_number" name="invoice_number" class="mt-1 block w-full" value="{{ old('invoice_number', $asset->invoice_number) }}" />
                </div>
            </div>
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Warranty Details</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="warranty_start" value="Warranty Start" />
                    <x-text-input id="warranty_start" type="date" name="warranty_start" class="mt-1 block w-full" value="{{ old('warranty_start', optional($asset->warranty_start)->format('Y-m-d')) }}" />
                </div>
                <div>
                    <x-input-label for="warranty_end" value="Warranty End" />
                    <x-text-input id="warranty_end" type="date" name="warranty_end" class="mt-1 block w-full" value="{{ old('warranty_end', optional($asset->warranty_end)->format('Y-m-d')) }}" />
                </div>
                <div>
                    <x-input-label for="warranty_provider" value="Warranty Provider" />
                    <x-text-input id="warranty_provider" name="warranty_provider" class="mt-1 block w-full" value="{{ old('warranty_provider', $asset->warranty_provider) }}" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="warranty_card_details" value="Warranty Card Details" />
                    <x-textarea-input id="warranty_card_details" name="warranty_card_details" rows="2" class="mt-1 block w-full">{{ old('warranty_card_details', $asset->warranty_card_details) }}</x-textarea-input>
                </div>
            </div>
        </x-card>

        <x-card>
            <h3 class="mb-1 text-sm font-semibold text-gray-500">Add Attachments</h3>
            <p class="mb-3 text-xs text-gray-400">These are added immediately, regardless of Admin approval on the details above.</p>
            <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.mp4,.mov,.avi" class="block w-full text-sm">
            @error('attachments')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </x-card>

        <x-card>
            <h3 class="mb-1 text-sm font-semibold text-gray-500">Add Reports</h3>
            <input type="file" name="reports[]" multiple accept=".jpg,.jpeg,.png,.pdf,.mp4,.mov,.avi" class="block w-full text-sm">
        </x-card>

        <div class="flex justify-end gap-2">
            <x-link-button :href="route('assets.show', $asset)" variant="secondary">Cancel</x-link-button>
            <x-primary-button>{{ $isAdmin ? 'Save Changes' : 'Submit for Approval' }}</x-primary-button>
        </div>
    </form>
</x-app-layout>
