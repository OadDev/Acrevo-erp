<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Generate Work Order" :subtitle="$quotation?->quotation_no" />
    </x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('work-orders.store') }}">
            @csrf
            <input type="hidden" name="quotation_id" value="{{ $quotation->id }}">
            <input type="hidden" name="client_id" value="{{ $quotation->client_id }}">
            <input type="hidden" name="site_id" value="{{ $site->id }}">
            <div class="mb-6 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                Generating from approved quotation for <strong>{{ $quotation->client->name }}</strong> — Total ₹{{ number_format($quotation->total_amount, 2) }}
            </div>

            <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-800 dark:bg-gray-900">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-400">Site ID (auto-generated)</p>
                <p class="text-lg font-semibold text-gray-900 dark:text-white">{{ $site->site_no }}</p>
            </div>

            <h3 class="mb-4 text-sm font-semibold text-gray-500 dark:text-gray-400">Site Details</h3>
            <div class="mb-8 grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="site_address" value="Address" />
                    <x-text-input id="site_address" name="site_address" class="mt-1 block w-full" value="{{ old('site_address', $site->address) }}" />
                </div>

                <div>
                    <x-input-label for="site_city" value="City" />
                    <x-text-input id="site_city" name="site_city" class="mt-1 block w-full" value="{{ old('site_city', $site->city) }}" />
                </div>

                <div>
                    <x-input-label for="site_state" value="State" />
                    <x-text-input id="site_state" name="site_state" class="mt-1 block w-full" value="{{ old('site_state', $site->state) }}" />
                </div>

                <div>
                    <x-input-label for="site_pincode" value="Pincode" />
                    <x-text-input id="site_pincode" name="site_pincode" class="mt-1 block w-full" value="{{ old('site_pincode', $site->pincode) }}" />
                </div>

                <div>
                    <x-input-label for="site_contact_name" value="Site Contact Name" />
                    <x-text-input id="site_contact_name" name="site_contact_name" class="mt-1 block w-full" value="{{ old('site_contact_name', $site->site_contact_name) }}" />
                </div>

                <div>
                    <x-input-label for="site_contact_phone" value="Site Contact Phone" />
                    <x-text-input id="site_contact_phone" name="site_contact_phone" class="mt-1 block w-full" value="{{ old('site_contact_phone', $site->site_contact_phone) }}" />
                </div>
            </div>

            <h3 class="mb-4 text-sm font-semibold text-gray-500 dark:text-gray-400">Work Order Details</h3>
            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="title" value="Work Order Title" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ old('title', $quotation?->enquiry?->service_type) }}" required />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="scope" value="Scope of Work" />
                    <x-textarea-input id="scope" name="scope" rows="3" class="mt-1 block w-full">{{ old('scope') }}</x-textarea-input>
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="execution_way" value="Execution Method" />
                    <x-select-input id="execution_way" name="execution_way" class="mt-1 block w-full" required>
                        <option value="" disabled selected>Select how this work will be carried out</option>
                        @foreach (\App\Models\WorkOrder::EXECUTION_WAYS as $value => $label)
                            <option value="{{ $value }}" @selected(old('execution_way') === $value)>{{ $label }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="priority" value="Priority" />
                    <x-select-input id="priority" name="priority" class="mt-1 block w-full">
                        @foreach (['low', 'medium', 'high', 'urgent'] as $p)
                            <option value="{{ $p }}" @selected($p === 'medium')>{{ ucfirst($p) }}</option>
                        @endforeach
                    </x-select-input>
                </div>

                <div>
                    <x-input-label for="budget_amount" value="Budget Amount" />
                    <x-text-input id="budget_amount" type="number" step="0.01" name="budget_amount" class="mt-1 block w-full" value="{{ old('budget_amount', $quotation?->total_amount) }}" />
                </div>

                <div>
                    <x-input-label for="start_date" value="Start Date" />
                    <x-text-input id="start_date" type="date" name="start_date" class="mt-1 block w-full" />
                </div>

                <div>
                    <x-input-label for="deadline" value="Deadline" />
                    <x-text-input id="deadline" type="date" name="deadline" class="mt-1 block w-full" />
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-primary-button>Generate Work Order</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
