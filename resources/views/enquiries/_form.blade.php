@csrf

@if (isset($client) && $client)
    <input type="hidden" name="client_id" value="{{ $client->id }}">
    <div class="mb-5 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
        Linked to existing client: <strong>{{ $client->name }}</strong>
    </div>
@endif

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <x-input-label for="contact_name" value="Contact Name" />
        <x-text-input id="contact_name" name="contact_name" class="mt-1 block w-full" value="{{ old('contact_name', $enquiry->contact_name ?? $client->name ?? '') }}" required />
        <x-input-error :messages="$errors->get('contact_name')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="contact_phone" value="Contact Phone" />
        <x-text-input id="contact_phone" name="contact_phone" class="mt-1 block w-full" value="{{ old('contact_phone', $enquiry->contact_phone ?? $client->phone ?? '') }}" required />
        <x-input-error :messages="$errors->get('contact_phone')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="contact_email" value="Contact Email" />
        <x-text-input id="contact_email" type="email" name="contact_email" class="mt-1 block w-full" value="{{ old('contact_email', $enquiry->contact_email ?? '') }}" />
        @isset($enquiry)
            <label class="mt-2 flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                <input type="checkbox" name="sync_to_client" value="1" class="rounded border-gray-300 text-indigo-600" @checked(old('sync_to_client'))>
                Update the client's email/address/city to match, even if they're already set
            </label>
        @endisset
    </div>

    <div>
        <x-input-label for="source" value="Source" />
        <x-select-input id="source" name="source" class="mt-1 block w-full">
            @foreach (['website' => 'Website', 'referral' => 'Referral', 'call' => 'Call', 'walk_in' => 'Walk-in', 'social_media' => 'Social Media', 'advertisement' => 'Advertisement', 'other' => 'Other'] as $value => $label)
                <option value="{{ $value }}" @selected(old('source', $enquiry->source ?? '') === $value)>{{ $label }}</option>
            @endforeach
        </x-select-input>
    </div>

    <div>
        <x-input-label for="service_type" value="Service Type" />
        <x-text-input id="service_type" name="service_type" class="mt-1 block w-full" value="{{ old('service_type', $enquiry->service_type ?? '') }}" placeholder="e.g. Interior Fit-out" />
    </div>

    <div>
        <x-input-label for="assigned_to" value="Assign To" />
        <x-select-input id="assigned_to" name="assigned_to" class="mt-1 block w-full">
            <option value="">Unassigned</option>
            @foreach ($salesUsers as $user)
                <option value="{{ $user->id }}" @selected(old('assigned_to', $enquiry->assigned_to ?? '') == $user->id)>{{ $user->name }}</option>
            @endforeach
        </x-select-input>
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="address" value="Site Address" />
        <x-textarea-input id="address" name="address" rows="2" class="mt-1 block w-full">{{ old('address', $enquiry->address ?? '') }}</x-textarea-input>
    </div>

    <div>
        <x-input-label for="city" value="City" />
        <x-text-input id="city" name="city" class="mt-1 block w-full" value="{{ old('city', $enquiry->city ?? '') }}" />
    </div>

    <div>
        <x-input-label for="follow_up_date" value="Next Follow-up Date" />
        <x-text-input id="follow_up_date" type="date" name="follow_up_date" class="mt-1 block w-full" value="{{ old('follow_up_date', optional($enquiry->follow_up_date ?? null)->format('Y-m-d')) }}" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="description" value="Requirement Description" />
        <x-textarea-input id="description" name="description" rows="3" class="mt-1 block w-full">{{ old('description', $enquiry->description ?? '') }}</x-textarea-input>
    </div>
</div>

<div class="mt-6 flex justify-end gap-2">
    <x-link-button :href="route('enquiries.index')" variant="secondary">Cancel</x-link-button>
    <x-primary-button>Save Enquiry</x-primary-button>
</div>
