<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$audit->title">
            <x-slot name="actions">
                <x-badge :status="$audit->status" class="text-sm" />
                @can('audit.manage')
                    <x-link-button :href="route('audits.edit', $audit)" variant="secondary">Edit</x-link-button>
                @endcan
                @can('audit.delete')
                    <form method="POST" action="{{ route('audits.destroy', $audit) }}" onsubmit="return confirm('Remove this audit and its files? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Delete</button>
                    </form>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <x-card class="max-w-2xl">
        <dl class="space-y-3 text-sm">
            <div><dt class="text-gray-400">Type</dt><dd><x-badge color="indigo" :status="$audit->type" /></dd></div>
            <div><dt class="text-gray-400">Work Order</dt><dd>{{ $audit->workOrder?->work_order_no ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Auditor</dt><dd>{{ $audit->auditor->name }}</dd></div>
            <div><dt class="text-gray-400">Date</dt><dd>{{ $audit->audit_date->format('d M Y') }}</dd></div>
            <div><dt class="text-gray-400">Findings</dt><dd class="whitespace-pre-line">{{ $audit->findings ?: '—' }}</dd></div>
        </dl>

        @if ($audit->media->isNotEmpty())
            <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-gray-400">Files</p>
                <div class="flex flex-wrap gap-3">
                    @foreach ($audit->media as $file)
                        <a href="{{ $file->getUrl() }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 px-3 py-1.5 text-sm text-indigo-600 hover:underline dark:border-gray-700">
                            <x-icon name="file-text" class="h-4 w-4" /> {{ $file->file_name }}
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </x-card>
</x-app-layout>
