@csrf

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <x-input-label for="name" value="Client Name" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name', $client->name ?? '') }}" required />
        <x-input-error :messages="$errors->get('name')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="type" value="Client Type" />
        <x-select-input id="type" name="type" class="mt-1 block w-full">
            @foreach (['individual' => 'Individual', 'company' => 'Company'] as $value => $label)
                <option value="{{ $value }}" @selected(old('type', $client->type ?? 'individual') === $value)>{{ $label }}</option>
            @endforeach
        </x-select-input>
    </div>

    <div>
        <x-input-label for="phone" value="Phone" />
        <x-text-input id="phone" name="phone" class="mt-1 block w-full" value="{{ old('phone', $client->phone ?? '') }}" required />
        <x-input-error :messages="$errors->get('phone')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="email" value="Email" />
        <x-text-input id="email" type="email" name="email" class="mt-1 block w-full" value="{{ old('email', $client->email ?? '') }}" />
    </div>

    <div>
        <x-input-label for="assigned_sales_user_id" value="Assigned Sales Executive" />
        <x-select-input id="assigned_sales_user_id" name="assigned_sales_user_id" class="mt-1 block w-full">
            <option value="">Unassigned</option>
            @foreach ($salesUsers as $user)
                <option value="{{ $user->id }}" @selected(old('assigned_sales_user_id', $client->assigned_sales_user_id ?? '') == $user->id)>{{ $user->name }}</option>
            @endforeach
        </x-select-input>
    </div>

    <div>
        <x-input-label for="gstin" value="GSTIN" />
        <x-text-input id="gstin" name="gstin" class="mt-1 block w-full" value="{{ old('gstin', $client->gstin ?? '') }}" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="address" value="Address" />
        <x-textarea-input id="address" name="address" rows="2" class="mt-1 block w-full">{{ old('address', $client->address ?? '') }}</x-textarea-input>
    </div>

    <div>
        <x-input-label for="city" value="City" />
        <x-text-input id="city" name="city" class="mt-1 block w-full" value="{{ old('city', $client->city ?? '') }}" />
    </div>

    <div>
        <x-input-label for="state" value="State" />
        <x-text-input id="state" name="state" class="mt-1 block w-full" value="{{ old('state', $client->state ?? '') }}" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="notes" value="Notes" />
        <x-textarea-input id="notes" name="notes" rows="3" class="mt-1 block w-full">{{ old('notes', $client->notes ?? '') }}</x-textarea-input>
    </div>
</div>

<div class="mt-6 flex justify-end gap-2">
    <x-link-button :href="route('clients.index')" variant="secondary">Cancel</x-link-button>
    <x-primary-button>Save Client</x-primary-button>
</div>
