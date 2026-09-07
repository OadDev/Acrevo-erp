<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Verification" :subtitle="$verification->asset->asset_code . ' — ' . $verification->asset->name" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('asset-verifications.update', $verification) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="result" value="Result" />
                <x-select-input id="result" name="result" class="mt-1 block w-full">
                    @foreach (\App\Models\AssetVerification::RESULTS as $result)
                        <option value="{{ $result }}" @selected(old('result', $verification->result) === $result)>{{ ucwords(str_replace('_', ' ', $result)) }}</option>
                    @endforeach
                </x-select-input>
            </div>
            <div>
                <x-input-label for="condition" value="Condition" />
                <x-text-input id="condition" name="condition" class="mt-1 block w-full" value="{{ old('condition', $verification->condition) }}" />
            </div>
            <div>
                <x-input-label for="verified_at" value="Verified On" />
                <x-text-input id="verified_at" type="date" name="verified_at" class="mt-1 block w-full" value="{{ old('verified_at', $verification->verified_at->format('Y-m-d')) }}" required />
            </div>
            <div>
                <x-input-label for="remarks" value="Remarks" />
                <x-textarea-input id="remarks" name="remarks" rows="2" class="mt-1 block w-full">{{ old('remarks', $verification->remarks) }}</x-textarea-input>
            </div>

            <div class="flex justify-end gap-2">
                <x-link-button :href="route('assets.show', $verification->asset_id)" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
