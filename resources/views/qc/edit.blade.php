<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Edit QC Inspection" :subtitle="$qcInspection->workOrder->work_order_no.' — '.$qcInspection->workOrder->title" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('qc.update', $qcInspection) }}">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <div>
                    <x-input-label for="inspection_type" value="Inspection Type" />
                    <x-select-input id="inspection_type" name="inspection_type" class="mt-1 block w-full">
                        <option value="daily" @selected(old('inspection_type', $qcInspection->inspection_type) === 'daily')>Daily QC</option>
                        <option value="final" @selected(old('inspection_type', $qcInspection->inspection_type) === 'final')>Final QC</option>
                    </x-select-input>
                </div>

                <div>
                    <x-input-label value="Result" />
                    <div class="mt-2 flex gap-4">
                        @foreach (['passed' => 'Passed', 'failed' => 'Failed', 'rework_required' => 'Rework Required'] as $value => $label)
                            <label class="flex items-center gap-1.5 text-sm">
                                <input type="radio" name="status" value="{{ $value }}" @checked(old('status', $qcInspection->status) === $value) required class="text-indigo-600 focus:ring-indigo-500">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div>
                    <x-input-label for="remarks" value="Remarks" />
                    <x-textarea-input id="remarks" name="remarks" rows="3" class="mt-1 block w-full">{{ old('remarks', $qcInspection->remarks) }}</x-textarea-input>
                </div>
            </div>

            <div class="mt-6 flex justify-end gap-2">
                <x-link-button :href="route('qc.show', $qcInspection)" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Save Changes</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
