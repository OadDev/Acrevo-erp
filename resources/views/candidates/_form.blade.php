@csrf

<div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
    <div>
        <x-input-label for="name" value="Full Name" />
        <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name', $candidate->name ?? '') }}" required />
    </div>

    <div>
        <x-input-label for="position_applied" value="Position Applied For" />
        <x-text-input id="position_applied" name="position_applied" class="mt-1 block w-full" value="{{ old('position_applied', $candidate->position_applied ?? '') }}" />
    </div>

    <div>
        <x-input-label for="phone" value="Phone" />
        <x-text-input id="phone" name="phone" class="mt-1 block w-full" value="{{ old('phone', $candidate->phone ?? '') }}" />
    </div>

    <div>
        <x-input-label for="email" value="Email" />
        <x-text-input id="email" type="email" name="email" class="mt-1 block w-full" value="{{ old('email', $candidate->email ?? '') }}" />
    </div>

    <div>
        <x-input-label for="department_id" value="Department" />
        <x-select-input id="department_id" name="department_id" class="mt-1 block w-full">
            <option value="">—</option>
            @foreach ($departments as $department)
                <option value="{{ $department->id }}" @selected(old('department_id', $candidate->department_id ?? '') == $department->id)>{{ $department->name }}</option>
            @endforeach
        </x-select-input>
    </div>

    <div>
        <x-input-label for="qualification" value="Qualification" />
        <x-text-input id="qualification" name="qualification" class="mt-1 block w-full" value="{{ old('qualification', $candidate->qualification ?? '') }}" />
    </div>

    <div>
        <x-input-label for="expected_salary" value="Expected Salary" />
        <x-text-input id="expected_salary" type="number" step="0.01" name="expected_salary" class="mt-1 block w-full" value="{{ old('expected_salary', $candidate->expected_salary ?? '') }}" />
    </div>

    <div>
        <x-input-label for="interview_date" value="Interview Date" />
        <x-text-input id="interview_date" type="date" name="interview_date" class="mt-1 block w-full" value="{{ old('interview_date', optional($candidate->interview_date ?? null)->format('Y-m-d')) }}" />
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="experience_summary" value="Experience Summary" />
        <x-textarea-input id="experience_summary" name="experience_summary" rows="2" class="mt-1 block w-full">{{ old('experience_summary', $candidate->experience_summary ?? '') }}</x-textarea-input>
    </div>

    <div class="sm:col-span-2">
        <x-input-label for="interview_notes" value="Interview Notes" />
        <x-textarea-input id="interview_notes" name="interview_notes" rows="3" placeholder="How the interview went, strengths, concerns, etc." class="mt-1 block w-full">{{ old('interview_notes', $candidate->interview_notes ?? '') }}</x-textarea-input>
    </div>

    <div class="sm:col-span-2">
        <x-input-label value="{{ isset($candidate) ? 'Add More Documents' : 'Resume / Documents (one or more)' }}" />
        <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="mt-1 block w-full text-sm">
        @error('files')<p class="mt-1 text-xs text-rose-600">{{ $message }}</p>@enderror
    </div>

    @if (isset($candidate) && $candidate->media->isNotEmpty())
        <div class="sm:col-span-2">
            <x-input-label value="Uploaded Documents" />
            <div class="mt-1 divide-y divide-gray-100 rounded-lg border border-gray-200 dark:divide-gray-800 dark:border-gray-800">
                @foreach ($candidate->media as $file)
                    <div class="flex items-center justify-between gap-2 px-3 py-2 text-sm">
                        <a href="{{ $file->getUrl() }}" target="_blank" class="flex flex-1 items-center gap-2 truncate">
                            <x-icon name="file-text" class="h-4 w-4 shrink-0 text-gray-400" />
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $file->file_name }}</span>
                        </a>
                        <form method="POST" action="{{ route('candidates.media.destroy', [$candidate, $file]) }}" onsubmit="return confirm('Remove this file?')">
                            @csrf
                            @method('DELETE')
                            <button class="shrink-0 text-xs font-medium text-rose-600 hover:underline">Remove</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>

<div class="mt-6 flex justify-end gap-2">
    <x-link-button :href="route('candidates.index')" variant="secondary">Cancel</x-link-button>
    <x-primary-button>Save Candidate</x-primary-button>
</div>
