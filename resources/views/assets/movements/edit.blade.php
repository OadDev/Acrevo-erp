<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Movement" :subtitle="$movement->asset->asset_code . ' — ' . $movement->asset->name" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('asset-movements.update', $movement) }}" class="space-y-4" x-data="{ toLocation: '{{ old('to_location', $movement->to_location) }}' }">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="type" value="Movement Type" />
                <x-select-input id="type" name="type" class="mt-1 block w-full">
                    @foreach (\App\Models\AssetMovement::TYPES as $type)
                        @continue($type === 'purchase')
                        <option value="{{ $type }}" @selected(old('type', $movement->type) === $type)>{{ ucwords(str_replace('_', ' ', $type)) }}</option>
                    @endforeach
                </x-select-input>
            </div>
            <div>
                <x-input-label for="to_location" value="To Location" />
                <x-select-input id="to_location" name="to_location" class="mt-1 block w-full" x-model="toLocation">
                    @foreach (\App\Models\AssetMovement::LOCATIONS as $location)
                        <option value="{{ $location }}" @selected(old('to_location', $movement->to_location) === $location)>{{ ucwords(str_replace('_', ' ', $location)) }}</option>
                    @endforeach
                </x-select-input>
            </div>
            <div x-show="toLocation === 'work_order'">
                <x-input-label for="to_work_order_id" value="Work Order" />
                <x-select-input id="to_work_order_id" name="to_work_order_id" class="mt-1 block w-full">
                    <option value="">—</option>
                    @foreach ($workOrders as $wo)
                        <option value="{{ $wo->id }}" @selected(old('to_work_order_id', $movement->to_work_order_id) == $wo->id)>{{ $wo->work_order_no }}</option>
                    @endforeach
                </x-select-input>
            </div>
            <div>
                <x-input-label for="moved_at" value="Date" />
                <x-text-input id="moved_at" type="date" name="moved_at" class="mt-1 block w-full" value="{{ old('moved_at', $movement->moved_at->format('Y-m-d')) }}" required />
            </div>
            <div>
                <x-input-label for="remarks" value="Remarks" />
                <x-textarea-input id="remarks" name="remarks" rows="2" class="mt-1 block w-full">{{ old('remarks', $movement->remarks) }}</x-textarea-input>
            </div>

            <div class="flex justify-end gap-2">
                <x-link-button :href="route('assets.show', $movement->asset_id)" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
