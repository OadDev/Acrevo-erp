<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Equipment Request" :subtitle="$equipmentRequest->item_name" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('equipment-requests.update', $equipmentRequest) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="item_name" value="Item Name" />
                <x-text-input id="item_name" name="item_name" class="mt-1 block w-full" value="{{ old('item_name', $equipmentRequest->item_name) }}" required />
            </div>
            <div>
                <x-input-label for="category" value="Category" />
                <x-text-input id="category" name="category" class="mt-1 block w-full" value="{{ old('category', $equipmentRequest->category) }}" />
            </div>
            <div>
                <x-input-label for="quantity" value="Quantity" />
                <x-text-input id="quantity" type="number" min="1" name="quantity" class="mt-1 block w-full" value="{{ old('quantity', $equipmentRequest->quantity) }}" required />
            </div>
            <div>
                <x-input-label for="work_order_id" value="Work Order" />
                <x-select-input id="work_order_id" name="work_order_id" class="mt-1 block w-full">
                    <option value="">Company Store / No Specific Site</option>
                    @foreach ($workOrders as $wo)
                        <option value="{{ $wo->id }}" @selected(old('work_order_id', $equipmentRequest->work_order_id) == $wo->id)>{{ $wo->work_order_no }}</option>
                    @endforeach
                </x-select-input>
            </div>
            <div>
                <x-input-label for="required_by_date" value="Required By" />
                <x-text-input id="required_by_date" type="date" name="required_by_date" class="mt-1 block w-full" value="{{ old('required_by_date', $equipmentRequest->required_by_date?->format('Y-m-d')) }}" />
            </div>
            <div>
                <x-input-label for="reason" value="Reason" />
                <x-textarea-input id="reason" name="reason" rows="2" class="mt-1 block w-full">{{ old('reason', $equipmentRequest->reason) }}</x-textarea-input>
            </div>

            <div class="flex justify-end gap-2">
                <x-link-button :href="route('equipment-requests.index')" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
