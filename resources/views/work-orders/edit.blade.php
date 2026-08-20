<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit Work Order" :subtitle="$workOrder->work_order_no" />
    </x-slot>

    <x-card class="max-w-2xl">
        <form method="POST" action="{{ route('work-orders.update', $workOrder) }}">
            @csrf
            @method('PUT')

            <div class="space-y-5">
                <div>
                    <x-input-label for="title" value="Work Order Title" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" value="{{ old('title', $workOrder->title) }}" required />
                </div>

                <div>
                    <x-input-label for="scope" value="Scope of Work" />
                    <x-textarea-input id="scope" name="scope" rows="3" class="mt-1 block w-full">{{ old('scope', $workOrder->scope) }}</x-textarea-input>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <x-input-label for="priority" value="Priority" />
                        <x-select-input id="priority" name="priority" class="mt-1 block w-full">
                            @foreach (['low', 'medium', 'high', 'urgent'] as $p)
                                <option value="{{ $p }}" @selected(old('priority', $workOrder->priority) === $p)>{{ ucfirst($p) }}</option>
                            @endforeach
                        </x-select-input>
                    </div>

                    <div></div>

                    <div>
                        <x-input-label for="start_date" value="Start Date" />
                        <x-text-input id="start_date" type="date" name="start_date" class="mt-1 block w-full" value="{{ old('start_date', optional($workOrder->start_date)->format('Y-m-d')) }}" />
                    </div>

                    <div>
                        <x-input-label for="deadline" value="Deadline" />
                        <x-text-input id="deadline" type="date" name="deadline" class="mt-1 block w-full" value="{{ old('deadline', optional($workOrder->deadline)->format('Y-m-d')) }}" />
                    </div>

                    <div>
                        <x-input-label for="estimated_material_budget" value="Allocated Material Budget" />
                        <x-text-input id="estimated_material_budget" type="text" inputmode="decimal" name="estimated_material_budget" class="mt-1 block w-full" value="{{ old('estimated_material_budget', $workOrder->estimated_material_budget) }}" />
                    </div>

                    <div>
                        <x-input-label for="estimated_labour_budget" value="Allocated Man Power Budget" />
                        <x-text-input id="estimated_labour_budget" type="text" inputmode="decimal" name="estimated_labour_budget" class="mt-1 block w-full" value="{{ old('estimated_labour_budget', $workOrder->estimated_labour_budget) }}" />
                    </div>
                </div>

                <p class="text-xs text-gray-400">Execution method, site, client, and itemized entries (materials, man power, ledger, etc.) are edited from their own tabs.</p>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <a href="{{ route('work-orders.show', $workOrder) }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Cancel</a>
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
