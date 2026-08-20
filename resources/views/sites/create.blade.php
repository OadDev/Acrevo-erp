<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Add Site" :subtitle="$client ? 'New site for '.$client->name : 'Add a site for a client'" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('sites.store') }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <x-input-label for="client_id" value="Client" />
                    <x-select-input id="client_id" name="client_id" class="mt-1 block w-full" required :disabled="(bool) $client">
                        <option value="">Select a client</option>
                        @foreach ($clients as $c)
                            <option value="{{ $c->id }}" @selected(old('client_id', $client?->id) == $c->id)>{{ $c->name }}</option>
                        @endforeach
                    </x-select-input>
                    @if ($client)
                        <input type="hidden" name="client_id" value="{{ $client->id }}" />
                    @endif
                </div>
                <div>
                    <x-input-label for="address" value="Address" />
                    <x-text-input id="address" name="address" class="mt-1 block w-full" value="{{ old('address') }}" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="city" value="City" />
                        <x-text-input id="city" name="city" class="mt-1 block w-full" value="{{ old('city') }}" />
                    </div>
                    <div>
                        <x-input-label for="state" value="State" />
                        <x-text-input id="state" name="state" class="mt-1 block w-full" value="{{ old('state') }}" />
                    </div>
                </div>
                <div>
                    <x-input-label for="pincode" value="Pincode" />
                    <x-text-input id="pincode" name="pincode" class="mt-1 block w-full" value="{{ old('pincode') }}" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="site_contact_name" value="Site Contact Name" />
                        <x-text-input id="site_contact_name" name="site_contact_name" class="mt-1 block w-full" value="{{ old('site_contact_name') }}" />
                    </div>
                    <div>
                        <x-input-label for="site_contact_phone" value="Site Contact Phone" />
                        <x-text-input id="site_contact_phone" name="site_contact_phone" class="mt-1 block w-full" value="{{ old('site_contact_phone') }}" />
                    </div>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-link-button :href="$client ? route('clients.show', $client) : route('sites.index')" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Add Site</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
