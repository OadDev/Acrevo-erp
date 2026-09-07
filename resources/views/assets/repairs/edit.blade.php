<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Repair Entry" :subtitle="$repair->asset->asset_code . ' — ' . $repair->asset->name" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('asset-repairs.update', $repair) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <x-input-label value="Location / Quantity" />
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    {{ $repair->location === 'work_order' && $repair->workOrder ? $repair->workOrder->work_order_no : ucwords(str_replace('_', ' ', $repair->location)) }} - {{ $repair->quantity }} unit(s)
                </p>
                <p class="mt-1 text-xs text-gray-400">Not editable, since it already moved stock between buckets. Remove and re-record this entry to change it.</p>
            </div>
            <div>
                <x-input-label for="repair_type" value="Repair Type" />
                <x-select-input id="repair_type" name="repair_type" class="mt-1 block w-full">
                    @foreach (\App\Models\AssetRepair::TYPES as $type)
                        <option value="{{ $type }}" @selected(old('repair_type', $repair->repair_type) === $type)>{{ ucwords($type) }}</option>
                    @endforeach
                </x-select-input>
            </div>
            <div>
                <x-input-label for="issue_description" value="Issue Description" />
                <x-textarea-input id="issue_description" name="issue_description" rows="3" class="mt-1 block w-full" required>{{ old('issue_description', $repair->issue_description) }}</x-textarea-input>
            </div>
            <div>
                <x-input-label for="technician_vendor" value="Technician / Vendor" />
                <x-text-input id="technician_vendor" name="technician_vendor" class="mt-1 block w-full" value="{{ old('technician_vendor', $repair->technician_vendor) }}" />
            </div>
            <div class="flex items-center gap-2">
                <input type="hidden" name="is_warranty_repair" value="0">
                <input type="checkbox" id="is_warranty_repair" name="is_warranty_repair" value="1" @checked(old('is_warranty_repair', $repair->is_warranty_repair)) class="rounded border-gray-300">
                <x-input-label for="is_warranty_repair" value="Warranty Repair" class="!mb-0" />
            </div>
            <div>
                <x-input-label for="cost" value="Cost" />
                <x-text-input id="cost" type="number" step="0.01" name="cost" class="mt-1 block w-full" value="{{ old('cost', $repair->cost) }}" />
            </div>
            <div>
                <x-input-label for="reported_date" value="Reported Date" />
                <x-text-input id="reported_date" type="date" name="reported_date" class="mt-1 block w-full" value="{{ old('reported_date', $repair->reported_date->format('Y-m-d')) }}" required />
            </div>
            <div>
                <x-input-label for="remarks" value="Remarks" />
                <x-textarea-input id="remarks" name="remarks" rows="2" class="mt-1 block w-full">{{ old('remarks', $repair->remarks) }}</x-textarea-input>
            </div>

            <div class="flex justify-end gap-2">
                <x-link-button :href="route('assets.show', $repair->asset_id)" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
