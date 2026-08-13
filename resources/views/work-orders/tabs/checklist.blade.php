<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <div class="space-y-4 lg:col-span-2">
        @forelse ($workOrder->dailyChecklists as $checklist)
            <x-card>
                <div class="mb-2 flex items-center justify-between">
                    <p class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $checklist->date->format('d M Y') }}</p>
                    <span class="text-xs text-gray-400">{{ $checklist->executiveTeam?->name ?? '—' }}</span>
                </div>
                <ul class="space-y-1 text-sm">
                    @foreach ($checklist->items ?? [] as $item)
                        <li class="flex items-center gap-2 text-gray-600 dark:text-gray-300">
                            <x-icon name="check-circle" class="h-4 w-4 text-emerald-500" /> {{ $item }}
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @empty
            <x-empty-state icon="clipboard" title="No checklists submitted yet" />
        @endforelse
    </div>

    @can('daily_checklist.manage')
        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Submit Today's Checklist</h3>
            <form method="POST" action="{{ route('work-orders.checklists.store', $workOrder) }}" class="space-y-3">
                @csrf
                <x-select-input name="executive_team_id" class="w-full" required>
                    @foreach ($workOrder->executiveTeams->whereNull('unassigned_at') as $assignment)
                        <option value="{{ $assignment->executive_team_id }}">{{ $assignment->executiveTeam?->name ?? '—' }}</option>
                    @endforeach
                </x-select-input>
                <x-textarea-input name="items" rows="5" class="w-full" placeholder="One task per line..." required></x-textarea-input>
                <x-primary-button class="w-full justify-center">Save Checklist</x-primary-button>
            </form>
        </x-card>
    @endcan
</div>
