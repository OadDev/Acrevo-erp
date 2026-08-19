<div class="grid grid-cols-1 gap-6 lg:grid-cols-3" x-data="{ openReport: null }">
    <div class="space-y-4 lg:col-span-2">
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Media Gallery</h3>
            <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                @forelse ($workOrder->media as $item)
                    <div class="group relative block aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                        <a href="{{ $item->getUrl() }}" target="_blank" class="block h-full w-full">
                            @if (str_starts_with($item->mime_type, 'image'))
                                <img src="{{ $item->getUrl() }}" class="h-full w-full object-cover">
                            @elseif (str_starts_with($item->mime_type, 'video'))
                                <div class="flex h-full w-full items-center justify-center">
                                    <x-icon name="video" class="h-6 w-6 text-gray-400" />
                                </div>
                            @else
                                <div class="flex h-full w-full items-center justify-center">
                                    <x-icon name="file-text" class="h-6 w-6 text-gray-400" />
                                </div>
                            @endif
                        </a>
                        <span class="absolute bottom-1 left-1 rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white">{{ Str::title(str_replace('_',' ',$item->collection_name)) }}</span>
                        @if (auth()->user()->hasRole('Admin'))
                            <form method="POST" action="{{ route('work-orders.media.destroy', [$workOrder, $item]) }}" onsubmit="return confirm('Remove this file?')" class="absolute right-1 top-1">
                                @csrf
                                @method('DELETE')
                                <button class="rounded bg-black/60 px-1.5 py-0.5 text-[10px] text-white hover:bg-rose-600">Delete</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="col-span-full text-sm text-gray-400">No media uploaded yet.</p>
                @endforelse
            </div>
        </x-card>

        <x-card :padded="false">
            <h3 class="p-4 pb-0 text-sm font-semibold text-gray-500">Details Upload — Every File Stored on This Work Order</h3>
            <div class="divide-y divide-gray-100 dark:divide-gray-800">
                @forelse ($workOrder->media as $item)
                    <div class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-gray-50 dark:hover:bg-gray-800/40">
                        <a href="{{ $item->getUrl() }}" target="_blank" class="flex flex-1 items-center gap-2 truncate">
                            <x-icon name="file-text" class="h-4 w-4 shrink-0 text-gray-400" />
                            <span class="truncate text-gray-700 dark:text-gray-300">{{ $item->file_name }}</span>
                        </a>
                        <div class="flex shrink-0 items-center gap-3 text-xs text-gray-400">
                            <span>{{ Str::title(str_replace('_', ' ', $item->collection_name)) }}</span>
                            <span>{{ $item->created_at->format('d M Y') }}</span>
                            @if (auth()->user()->hasRole('Admin'))
                                <form method="POST" action="{{ route('work-orders.media.destroy', [$workOrder, $item]) }}" onsubmit="return confirm('Remove this file?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="font-medium text-rose-600 hover:underline">Delete</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-gray-400">No files uploaded yet.</p>
                @endforelse
            </div>
            @can('media.upload')
                <form method="POST" action="{{ route('work-orders.media.store', $workOrder) }}" enctype="multipart/form-data" class="grid grid-cols-2 gap-2 border-t border-gray-100 p-4 dark:border-gray-800 sm:grid-cols-4">
                    @csrf
                    <x-select-input name="collection" class="text-sm">
                        <option value="documents">Document</option>
                        <option value="images">Image</option>
                        <option value="videos">Video</option>
                        <option value="other">Other</option>
                    </x-select-input>
                    <input type="file" name="file" class="col-span-2 text-sm sm:col-span-2" required>
                    <x-primary-button class="justify-center">Upload</x-primary-button>
                </form>
            @endcan
        </x-card>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Daily Progress Reports</h3>
            @forelse ($workOrder->dailyProgressReports as $report)
                <div class="border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                    <div class="flex items-center justify-between">
                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ $report->date->format('d M Y') }} — {{ $report->executiveTeam?->name ?? '—' }}</p>
                        @if (auth()->user()->hasRole('Admin'))
                            <div class="flex items-center gap-2">
                                <button type="button" @click="openReport === {{ $report->id }} ? openReport = null : openReport = {{ $report->id }}" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                                <form method="POST" action="{{ route('work-orders.progress.destroy', [$workOrder, $report]) }}" onsubmit="return confirm('Remove this progress report?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="text-xs font-medium text-rose-600 hover:underline">Delete</button>
                                </form>
                            </div>
                        @endif
                    </div>
                    <p class="mt-1 text-gray-600 dark:text-gray-300"><span class="text-gray-400">Completed:</span> {{ $report->completed_work }}</p>
                    @if ($report->pending_work)<p class="text-gray-600 dark:text-gray-300"><span class="text-gray-400">Pending:</span> {{ $report->pending_work }}</p>@endif
                    @if ($report->problems)<p class="text-amber-600"><span class="text-gray-400">Problems:</span> {{ $report->problems }}</p>@endif
                    @if ($report->materials_required)<p class="text-gray-600 dark:text-gray-300"><span class="text-gray-400">Materials Needed:</span> {{ $report->materials_required }}</p>@endif

                    @if (auth()->user()->hasRole('Admin'))
                        <form method="POST" action="{{ route('work-orders.progress.update', [$workOrder, $report]) }}" x-show="openReport === {{ $report->id }}" x-cloak class="mt-2 space-y-2 rounded-lg border border-gray-100 p-2.5 dark:border-gray-800">
                            @csrf
                            @method('PUT')
                            <x-select-input name="executive_team_id" class="w-full text-xs">
                                @foreach ($workOrder->executiveTeams->whereNull('unassigned_at') as $assignment)
                                    <option value="{{ $assignment->executive_team_id }}" @selected($assignment->executive_team_id === $report->executive_team_id)>{{ $assignment->executiveTeam?->name ?? '—' }}</option>
                                @endforeach
                            </x-select-input>
                            <x-text-input type="date" name="date" value="{{ $report->date->format('Y-m-d') }}" class="w-full text-xs" required />
                            <x-textarea-input name="completed_work" rows="2" class="w-full text-xs" required>{{ $report->completed_work }}</x-textarea-input>
                            <x-textarea-input name="pending_work" rows="2" class="w-full text-xs">{{ $report->pending_work }}</x-textarea-input>
                            <x-textarea-input name="problems" rows="2" class="w-full text-xs">{{ $report->problems }}</x-textarea-input>
                            <x-textarea-input name="materials_required" rows="2" class="w-full text-xs">{{ $report->materials_required }}</x-textarea-input>
                            <button class="w-full rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Save</button>
                        </form>
                    @endif
                </div>
            @empty
                <x-empty-state icon="clipboard" title="No progress reports yet" />
            @endforelse
        </x-card>
    </div>

    <div class="space-y-6">
        @can('daily_progress.manage')
            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Submit Progress</h3>
                <form method="POST" action="{{ route('work-orders.progress.store', $workOrder) }}" class="space-y-3">
                    @csrf
                    <x-select-input name="executive_team_id" class="w-full" required>
                        @foreach ($workOrder->executiveTeams->whereNull('unassigned_at') as $assignment)
                            <option value="{{ $assignment->executive_team_id }}">{{ $assignment->executiveTeam?->name ?? '—' }}</option>
                        @endforeach
                    </x-select-input>
                    <x-text-input type="date" name="date" value="{{ now()->toDateString() }}" class="w-full" required />
                    <x-textarea-input name="completed_work" rows="2" class="w-full" placeholder="Completed work" required></x-textarea-input>
                    <x-textarea-input name="pending_work" rows="2" class="w-full" placeholder="Pending work"></x-textarea-input>
                    <x-textarea-input name="problems" rows="2" class="w-full" placeholder="Problems encountered"></x-textarea-input>
                    <x-textarea-input name="materials_required" rows="2" class="w-full" placeholder="Materials required"></x-textarea-input>
                    <x-primary-button class="w-full justify-center">Save Report</x-primary-button>
                </form>
            </x-card>
        @endcan

        @can('media.upload')
            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Upload Media</h3>
                <form method="POST" action="{{ route('work-orders.media.store', $workOrder) }}" enctype="multipart/form-data" class="space-y-3">
                    @csrf
                    <x-select-input name="collection" class="w-full">
                        <option value="documents">Document</option>
                        <option value="images">Image</option>
                        <option value="videos">Video</option>
                        <option value="other">Other</option>
                    </x-select-input>
                    <input type="file" name="file" class="w-full text-sm" required>
                    <x-primary-button class="w-full justify-center">Upload</x-primary-button>
                </form>
            </x-card>
        @endcan
    </div>
</div>
