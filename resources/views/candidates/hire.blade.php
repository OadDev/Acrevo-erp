<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Hire Candidate" :subtitle="$candidate->name" />
    </x-slot>

    <x-card class="max-w-3xl">
        <form method="POST" action="{{ route('candidates.hire', $candidate) }}">
            @csrf

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div>
                    <x-input-label for="name" value="Full Name" />
                    <x-text-input id="name" name="name" class="mt-1 block w-full" value="{{ old('name', $candidate->name) }}" required />
                    <x-input-error :messages="$errors->get('name')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="designation" value="Designation" />
                    <x-text-input id="designation" name="designation" class="mt-1 block w-full" value="{{ old('designation', $candidate->position_applied) }}" />
                </div>

                <div>
                    <x-input-label for="phone" value="Phone" />
                    <x-text-input id="phone" name="phone" class="mt-1 block w-full" value="{{ old('phone', $candidate->phone) }}" />
                </div>

                <div>
                    <x-input-label for="email" value="Email" />
                    <x-text-input id="email" type="email" name="email" class="mt-1 block w-full" value="{{ old('email', $candidate->email) }}" />
                </div>

                <div>
                    <x-input-label for="department_id" value="Department" />
                    <x-select-input id="department_id" name="department_id" class="mt-1 block w-full">
                        <option value="">—</option>
                        @foreach ($departments as $department)
                            <option value="{{ $department->id }}" @selected(old('department_id', $candidate->department_id) == $department->id)>{{ $department->name }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="employment_type" value="Employment Type" />
                    <x-select-input id="employment_type" name="employment_type" class="mt-1 block w-full">
                        @foreach (['permanent' => 'Permanent', 'contract' => 'Contract', 'daily_wage' => 'Daily Wage'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('employment_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="joining_date" value="Joining Date" />
                    <x-text-input id="joining_date" type="date" name="joining_date" class="mt-1 block w-full" value="{{ old('joining_date', now()->format('Y-m-d')) }}" />
                </div>

                <div>
                    <x-input-label for="salary_type" value="Salary Type" />
                    <x-select-input id="salary_type" name="salary_type" class="mt-1 block w-full">
                        @foreach (['monthly' => 'Monthly', 'daily' => 'Daily', 'hourly' => 'Hourly'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('salary_type') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="salary_amount" value="Salary Amount" />
                    <x-text-input id="salary_amount" type="number" step="0.01" name="salary_amount" class="mt-1 block w-full" value="{{ old('salary_amount', $candidate->expected_salary) }}" />
                </div>

                <div>
                    <x-input-label for="qualification" value="Qualification" />
                    <x-text-input id="qualification" name="qualification" class="mt-1 block w-full" value="{{ old('qualification', $candidate->qualification) }}" />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="experience_summary" value="Work Experience" />
                    <x-textarea-input id="experience_summary" name="experience_summary" rows="2" class="mt-1 block w-full">{{ old('experience_summary', $candidate->experience_summary) }}</x-textarea-input>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-link-button :href="route('candidates.show', $candidate)" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Hire &amp; Add to Worker List</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
