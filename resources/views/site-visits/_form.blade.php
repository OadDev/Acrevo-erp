@csrf
<input type="hidden" name="enquiry_id" value="{{ $enquiry->id }}">

<div class="mb-5 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
    {{ $enquiry->enquiry_no }} — {{ $enquiry->contact_name }}
</div>

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <x-input-label for="scheduled_at" value="Scheduled Date &amp; Time" />
        <x-text-input id="scheduled_at" type="datetime-local" name="scheduled_at" class="mt-1 block w-full" value="{{ old('scheduled_at', isset($siteVisit) ? $siteVisit->scheduled_at->format('Y-m-d\TH:i') : '') }}" required />
        <x-input-error :messages="$errors->get('scheduled_at')" class="mt-1" />
    </div>

    <div>
        <x-input-label for="assigned_to" value="Assigned To" />
        <x-select-input id="assigned_to" name="assigned_to" class="mt-1 block w-full" required>
            @foreach ($salesUsers as $user)
                <option value="{{ $user->id }}" @selected(old('assigned_to', $siteVisit->assigned_to ?? auth()->id()) == $user->id)>{{ $user->name }}</option>
            @endforeach
        </x-select-input>
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="notes" value="Notes" />
        <x-textarea-input id="notes" name="notes" rows="3" class="mt-1 block w-full">{{ old('notes', $siteVisit->notes ?? '') }}</x-textarea-input>
    </div>
</div>

<div class="mt-6 flex justify-end gap-2">
    <x-link-button :href="route('site-visits.index')" variant="secondary">Cancel</x-link-button>
    <x-primary-button>Save Site Visit</x-primary-button>
</div>
