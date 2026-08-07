<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Generate Work Order" :subtitle="$quotation?->quotation_no" />
    </x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('work-orders.store') }}">
            @csrf
            @if ($quotation)
                <input type="hidden" name="quotation_id" value="{{ $quotation->id }}">
                <input type="hidden" name="client_id" value="{{ $quotation->client_id }}">
                <div class="mb-5 rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">
                    Generating from approved quotation for <strong>{{ $quotation->client->name }}</strong> — Total ₹{{ number_format($quotation->total_amount, 2) }}
                </div>
            @else
                <div class="mb-5">
                    <x-input-label for="client_id" value="Client" />
                    <x-select-input id="client_id" name="client_id" class="mt-1 block w-full" required>
                        @foreach (\App\Models\Client::orderBy('name')->get() as $client)
                            <option value="{{ $client->id }}">{{ $client->name }}</option>
                        @endforeach
                    </x-select-input>
                </div>
            @endif

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <x-input-label for="title" value="Work Order Title" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ old('title', $quotation?->enquiry?->service_type) }}" required />
                </div>

                <div class="sm:col-span-2">
                    <x-input-label for="scope" value="Scope of Work" />
                    <x-textarea-input id="scope" name="scope" rows="3" class="mt-1 block w-full">{{ old('scope') }}</x-textarea-input>
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
