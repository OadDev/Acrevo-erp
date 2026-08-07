@csrf

<div class="space-y-4">
    <div>
        <x-input-label for="type" value="Record Type" />
        <x-select-input id="type" name="type" class="mt-1 block w-full">
            @foreach (['gst' => 'GST', 'msme' => 'MSME', 'insurance' => 'Insurance', 'license' => 'License', 'patent' => 'Patent'] as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $record->type ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </x-select-input>
    </div>
    <div>
        <x-input-label for="name" value="Name" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name', $record->name ?? '') }}" required />
    </div>
    <div>
        <x-input-label for="number" value="Number" />
        <x-text-input id="number" name="number" class="mt-1 block w-full" value="{{ old('number', $record->number ?? '') }}" />
    </div>
    <div class="grid grid-cols-2 gap-4">
        <div>
            <x-input-label for="issued_date" value="Issued Date" />
            <x-text-input id="issued_date" type="date" name="issued_date" class="mt-1 block w-full" value="{{ old('issued_date', optional($record->issued_date ?? null)->format('Y-m-d')) }}" />
        </div>
        <div>
            <x-input-label for="expiry_date" value="Expiry Date" />
            <x-text-input id="expiry_date" type="date" name="expiry_date" class="mt-1 block w-full" value="{{ old('expiry_date', optional($record->expiry_date ?? null)->format('Y-m-d')) }}" />
        </div>
    </div>
    <div>
        <x-input-label for="notes" value="Notes" />
        <x-textarea-input id="notes" name="notes" rows="3" class="mt-1 block w-full">{{ old('notes', $record->notes ?? '') }}</x-textarea-input>
    </div>
</div>

<div class="mt-6 flex justify-end gap-2">
    <x-link-button :href="route('company-records.index')" variant="secondary">Cancel</x-link-button>
    <x-primary-button>Save Record</x-primary-button>
</div>
