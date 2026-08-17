<div class="grid grid-cols-1 gap-6 lg:grid-cols-3" x-data="{ open: null }">
    <div class="space-y-4 lg:col-span-2">
        @forelse ($workOrder->dailyChecklists as $checklist)
            <x-card>
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $checklist->title ?? 'Daily Work' }}</p>
                        <p class="text-xs text-gray-400">{{ $checklist->date->format('d M Y') }} · {{ $checklist->executiveTeam?->name ?? '—' }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        @php
                            $total = $checklist->checklistItems->count();
                            $done = $checklist->checklistItems->where('is_done', true)->count();
                        @endphp
                        @if ($total)
                            <span class="text-xs font-medium text-gray-500">{{ $done }}/{{ $total }} done</span>
                        @endif
                        @if (auth()->user()->hasRole('Admin'))
                            <button type="button" @click="open === '{{ $checklist->id }}' ? open = null : open = '{{ $checklist->id }}'" class="text-xs font-medium text-indigo-600 hover:underline">Edit</button>
                            <form method="POST" action="{{ route('work-orders.checklists.destroy', [$workOrder, $checklist]) }}" onsubmit="return confirm('Remove this daily work entry and all its items?')">
                                @csrf
                                @method('DELETE')
                                <button class="text-xs font-medium text-rose-600 hover:underline">Delete</button>
                            </form>
                        @endif
                    </div>
                </div>
                @if (auth()->user()->hasRole('Admin'))
                    <form method="POST" action="{{ route('work-orders.checklists.update', [$workOrder, $checklist]) }}" x-show="open === '{{ $checklist->id }}'" x-cloak class="mb-3 grid grid-cols-2 gap-2 rounded-lg border border-gray-100 p-2.5 dark:border-gray-800">
                        @csrf
                        @method('PUT')
                        <x-select-input name="executive_team_id" class="text-xs">
                            @foreach ($workOrder->executiveTeams->whereNull('unassigned_at') as $assignment)
                                <option value="{{ $assignment->executive_team_id }}" @selected($assignment->executive_team_id === $checklist->executive_team_id)>{{ $assignment->executiveTeam?->name ?? '—' }}</option>
                            @endforeach
                        </x-select-input>
                        <x-text-input name="title" value="{{ $checklist->title }}" class="text-xs" required />
                        <button class="col-span-2 rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-indigo-500">Save</button>
                    </form>
                @endif
                <ul class="space-y-2 text-sm">
                    @forelse ($checklist->checklistItems as $item)
                        <li class="rounded-lg border border-gray-100 p-2.5 dark:border-gray-800">
                            @if ($item->is_done)
                                <div class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                                    <x-icon name="check-circle" class="h-4 w-4 shrink-0 text-emerald-500" />
                                    <span class="flex-1 line-through decoration-gray-300">{{ $item->description }}</span>
                                    @if ($item->getFirstMedia('proof'))
                                        <a href="{{ $item->getFirstMediaUrl('proof') }}" target="_blank" class="text-xs text-indigo-600 hover:underline">Proof</a>
                                    @endif
                                    @if (auth()->user()->hasRole('Admin'))
                                        <form method="POST" action="{{ route('work-orders.checklist-items.destroy', [$workOrder, $item]) }}" onsubmit="return confirm('Remove this checklist item?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-xs font-medium text-rose-600 hover:underline">Delete</button>
                                        </form>
                                    @endif
                                </div>
                                <p class="mt-1 text-xs text-gray-400">Done by {{ $item->doneBy?->name ?? '—' }} · {{ $item->done_at?->diffForHumans() }}</p>
                            @else
                                <div class="flex items-center gap-2 text-gray-700 dark:text-gray-300">
                                    <x-icon name="clock" class="h-4 w-4 shrink-0 text-amber-500" />
                                    <span class="flex-1">{{ $item->description }}</span>
                                    @if (auth()->user()->hasRole('Admin'))
                                        <form method="POST" action="{{ route('work-orders.checklist-items.destroy', [$workOrder, $item]) }}" onsubmit="return confirm('Remove this checklist item?')">
                                            @csrf
                                            @method('DELETE')
                                            <button class="text-xs font-medium text-rose-600 hover:underline">Delete</button>
                                        </form>
                                    @endif
                                </div>
                                @can('daily_checklist.manage')
                                    <form method="POST" action="{{ route('work-orders.checklist-items.done', [$workOrder, $item]) }}" enctype="multipart/form-data" class="mt-2 flex flex-wrap items-center gap-2">
                                        @csrf
                                        <label class="flex items-center gap-1.5 text-xs text-gray-500">
                                            <input type="checkbox" required class="rounded border-gray-300 text-emerald-600 focus:ring-emerald-500"> Work done
                                        </label>
                                        <input type="file" name="proof" accept="image/*,video/*" required class="flex-1 text-xs">
                                        <button class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Mark Done</button>
                                    </form>
                                @endcan
                            @endif
                        </li>
                    @empty
                        <li class="text-sm text-gray-400">No checklist items.</li>
                    @endforelse
                </ul>
            </x-card>
        @empty
            <x-empty-state icon="clipboard" title="No daily work entries yet" />
        @endforelse
    </div>

    @can('daily_checklist.manage')
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Add Daily Work</h3>
            <form method="POST" action="{{ route('work-orders.checklists.store', $workOrder) }}" class="space-y-3">
                @csrf
                <x-select-input name="executive_team_id" class="w-full" required>
                    @foreach ($workOrder->executiveTeams->whereNull('unassigned_at') as $assignment)
                        <option value="{{ $assignment->executive_team_id }}">{{ $assignment->executiveTeam?->name ?? '—' }}</option>
                    @endforeach
                </x-select-input>
                <x-text-input name="title" class="w-full" placeholder="Daily work (e.g. Plastering - Ground floor)" required />
                <x-textarea-input name="items" rows="5" class="w-full" placeholder="One checklist item per line..." required></x-textarea-input>
                <x-primary-button class="w-full justify-center">Save Daily Work</x-primary-button>
            </form>
        </x-card>
    @endcan
</div>
