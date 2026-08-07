<x-app-layout>
    <x-slot name="header">
        <x-page-header title="New Audit" />
    </x-slot>

    <x-card class="max-w-xl">
        <form method="POST" action="{{ route('audits.store') }}">
            @csrf
            <div class="space-y-4">
                <div>
                    <x-input-label for="title" value="Title" />
                    <x-text-input id="title" name="title" class="mt-1 block w-full" required />
                </div>
                <div>
                    <x-input-label for="type" value="Audit Type" />
                    <x-select-input id="type" name="type" class="mt-1 block w-full">
                        <option value="internal">Internal</option>
                        <option value="financial">Financial</option>
                        <option value="project">Project</option>
                    </x-select-input>
                </div>
                <div>
                    <x-input-label for="work_order_id" value="Related Work Order (optional)" />
                    <x-select-input id="work_order_id" name="work_order_id" class="mt-1 block w-full">
                        <option value="">—</option>
                        @foreach (\App\Models\WorkOrder::orderByDesc('created_at')->limit(100)->get() as $wo)
                            <option value="{{ $wo->id }}">{{ $wo->work_order_no }}</option>
                        @endforeach
                    </x-select-input>
                </div>
                <div>
                    <x-input-label for="audit_date" value="Audit Date" />
                    <x-text-input id="audit_date" type="date" name="audit_date" class="mt-1 block w-full" value="{{ now()->format('Y-m-d') }}" required />
                </div>
                <div>
                    <x-input-label for="findings" value="Findings" />
                    <x-textarea-input id="findings" name="findings" rows="4" class="mt-1 block w-full"></x-textarea-input>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-link-button :href="route('audits.index')" variant="secondary">Cancel</x-link-button>
                <x-primary-button>Save Audit</x-primary-button>
            </div>
        </form>
    </x-card>
</x-app-layout>
