<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Asset" subtitle="An Asset ID is generated automatically once saved." />
    </x-slot>

    <form method="POST" action="{{ route('assets.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Asset Details</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Equipment / Tool Name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name') }}" required />
                </div>
                <div>
                    <x-input-label for="category" value="Category" />
                    <x-text-input id="category" name="category" class="mt-1 block w-full" value="{{ old('category') }}" placeholder="e.g. Mixer, Power Tool, Vehicle" />
                </div>
                <div>
                    <x-input-label for="brand" value="Brand" />
                    <x-text-input id="brand" name="brand" class="mt-1 block w-full" value="{{ old('brand') }}" />
                </div>
                <div>
                    <x-input-label for="model" value="Model" />
                    <x-text-input id="model" name="model" class="mt-1 block w-full" value="{{ old('model') }}" />
                </div>
                <div>
                    <x-input-label for="serial_number" value="Serial Number" />
                    <x-text-input id="serial_number" name="serial_number" class="mt-1 block w-full" value="{{ old('serial_number') }}" />
                </div>
                <div>
                    <x-input-label for="quantity" value="Quantity" />
                    <x-text-input id="quantity" type="number" min="1" name="quantity" class="mt-1 block w-full" value="{{ old('quantity', 1) }}" required />
                </div>
                <div>
                    <x-input-label for="condition" value="Current Condition" />
                    <x-text-input id="condition" name="condition" class="mt-1 block w-full" value="{{ old('condition') }}" placeholder="e.g. Good, Fair, Needs Service" />
                </div>
                <div>
                    <x-input-label for="status" value="Initial Status" />
                    <x-select-input id="status" name="status" class="mt-1 block w-full">
                        @foreach (\App\Models\Asset::STATUSES as $status)
                            <option value="{{ $status }}" @selected(old('status', 'available') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label for="current_location" value="Current Location" />
                    <x-select-input id="current_location" name="current_location" class="mt-1 block w-full" x-data x-on:change="$refs.woField.classList.toggle('hidden', $event.target.value !== 'work_order')">
                        @foreach (\App\Models\Asset::LOCATIONS as $location)
                            <option value="{{ $location }}" @selected(old('current_location', 'company_store') === $location)>{{ ucwords(str_replace('_', ' ', $location)) }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div x-ref="woField" class="{{ old('current_location') === 'work_order' ? '' : 'hidden' }}">
                    <x-input-label for="current_work_order_id" value="Work Order" />
                    <x-select-input id="current_work_order_id" name="current_work_order_id" class="mt-1 block w-full">
                        <option value="">—</option>
                        @foreach ($workOrders as $wo)
                            <option value="{{ $wo->id }}" @selected(old('current_work_order_id') == $wo->id)>{{ $wo->work_order_no }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="remarks" value="Remarks" />
                    <x-textarea-input id="remarks" name="remarks" rows="2" class="mt-1 block w-full">{{ old('remarks') }}</x-textarea-input>
                </div>
            </div>
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Purchase Details</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="purchase_date" value="Purchase Date" />
                    <x-text-input id="purchase_date" type="date" name="purchase_date" class="mt-1 block w-full" value="{{ old('purchase_date') }}" />
                </div>
                <div>
                    <x-input-label for="purchase_cost" value="Purchase Cost" />
                    <x-text-input id="purchase_cost" type="number" step="0.01" min="0" name="purchase_cost" class="mt-1 block w-full" value="{{ old('purchase_cost') }}" />
                </div>
                <div>
                    <x-input-label for="supplier" value="Supplier" />
                    <x-text-input id="supplier" name="supplier" class="mt-1 block w-full" value="{{ old('supplier') }}" />
                </div>
                <div>
                    <x-input-label for="invoice_number" value="Invoice Number" />
                    <x-text-input id="invoice_number" name="invoice_number" class="mt-1 block w-full" value="{{ old('invoice_number') }}" />
                </div>
            </div>
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Warranty Details</h3>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <x-input-label for="warranty_start" value="Warranty Start" />
                    <x-text-input id="warranty_start" type="date" name="warranty_start" class="mt-1 block w-full" value="{{ old('warranty_start') }}" />
                </div>
                <div>
                    <x-input-label for="warranty_end" value="Warranty End" />
                    <x-text-input id="warranty_end" type="date" name="warranty_end" class="mt-1 block w-full" value="{{ old('warranty_end') }}" />
                </div>
                <div>
                    <x-input-label for="warranty_provider" value="Warranty Provider" />
                    <x-text-input id="warranty_provider" name="warranty_provider" class="mt-1 block w-full" value="{{ old('warranty_provider') }}" />
                </div>
                <div class="sm:col-span-2">
                    <x-input-label for="warranty_card_details" value="Warranty Card Details" />
                    <x-textarea-input id="warranty_card_details" name="warranty_card_details" rows="2" class="mt-1 block w-full">{{ old('warranty_card_details') }}</x-textarea-input>
                </div>
            </div>
        </x-card>

        <x-card>
            <h3 class="mb-1 text-sm font-semibold text-gray-500">Attachments</h3>
            <p class="mb-3 text-xs text-gray-400">Asset photos/working video, purchase invoice, warranty documents, manuals, and other related documents.</p>
            <input type="file" name="attachments[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.mp4,.mov,.avi" class="block w-full text-sm">
            @error('attachments')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
        </x-card>

        <x-card>
            <h3 class="mb-1 text-sm font-semibold text-gray-500">Reports</h3>
            <p class="mb-3 text-xs text-gray-400">Asset-related reports (image, PDF, or video).</p>
            <input type="file" name="reports[]" multiple accept=".jpg,.jpeg,.png,.pdf,.mp4,.mov,.avi" class="block w-full text-sm">
        </x-card>

        <div class="flex justify-end gap-2">
            <x-link-button :href="route('assets.index')" variant="secondary">Cancel</x-link-button>
            <x-primary-button>Save Asset</x-primary-button>
        </div>
    </form>
</x-app-layout>
