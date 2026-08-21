@php
    $me = auth()->id();
    $isAssignee = $task->assigned_to === $me;
    $isVerifier = in_array($me, [$task->verifier_id, $task->assigned_by], true);
    $isAdmin = auth()->user()->hasRole('Admin');
@endphp

<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$task->title" :subtitle="$task->schedule ? 'Calendar Task &middot; '.Str::title($task->schedule->frequency) : 'Common Task'">
            <x-slot name="actions">
                @if ($task->isOverdue())
                    <x-badge status="overdue" />
                @else
                    <x-badge :status="$task->status" />
                @endif
                @if ($isVerifier || $isAdmin)
                    @if (! $task->schedule)
                        <a href="{{ route('tasks.edit', $task) }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-600 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-300 dark:hover:bg-gray-800">Edit</a>
                    @endif
                    <form method="POST" action="{{ route('tasks.destroy', $task) }}" onsubmit="return confirm('Remove this task? This cannot be undone.')">
                        @csrf
                        @method('DELETE')
                        <button class="rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Remove</button>
                    </form>
                @endif
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Task Details</h3>
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-gray-400">Description</dt><dd class="text-gray-800 dark:text-gray-200">{{ $task->description ?: '—' }}</dd></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div><dt class="text-gray-400">Assigned By</dt><dd class="text-gray-800 dark:text-gray-200">{{ $task->assignedBy?->name ?? 'System (Calendar)' }}</dd></div>
                        <div><dt class="text-gray-400">Assign To</dt><dd class="text-gray-800 dark:text-gray-200">{{ $task->assignedTo?->name }}</dd></div>
                        <div><dt class="text-gray-400">Assigned Date</dt><dd class="text-gray-800 dark:text-gray-200">{{ $task->created_at->format('d M Y') }}</dd></div>
                        <div><dt class="text-gray-400">Due Date</dt><dd class="{{ $task->isOverdue() ? 'font-semibold text-rose-600' : 'text-gray-800 dark:text-gray-200' }}">{{ $task->due_date->format('d M Y') }}</dd></div>
                        <div><dt class="text-gray-400">Verifier</dt><dd class="text-gray-800 dark:text-gray-200">{{ $task->verifier?->name ?? '—' }}</dd></div>
                    </div>
                </dl>

                @if ($task->getMedia('attachments')->isNotEmpty())
                    <div class="mt-4 border-t border-gray-100 pt-3 dark:border-gray-800">
                        <p class="mb-2 text-sm font-medium text-gray-500">Attachments from {{ $task->assignedBy?->name ?? 'Assigner' }}</p>
                        <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                            @foreach ($task->getMedia('attachments') as $item)
                                <a href="{{ $item->getUrl() }}" target="_blank" class="block aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                                    @if (str_starts_with($item->mime_type, 'image'))
                                        <img src="{{ $item->getUrl() }}" class="h-full w-full object-cover">
                                    @else
                                        <div class="flex h-full w-full flex-col items-center justify-center gap-1 p-2 text-center">
                                            <x-icon name="file-text" class="h-6 w-6 text-gray-400" />
                                            <span class="w-full truncate text-[10px] text-gray-500">{{ $item->file_name }}</span>
                                        </div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </x-card>

            @if ($task->parentTask)
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-700 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-300">
                    This is a retask of <a href="{{ route('tasks.show', $task->parentTask) }}" class="underline">{{ $task->parentTask->title }}</a>{{ $task->retask_note ? ' — '.$task->retask_note : '' }}.
                </div>
            @endif

            @if ($task->status === 'submitted' || $task->status === 'verified')
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Completion Details</h3>
                    <p class="text-sm text-gray-700 dark:text-gray-300">{{ $task->completion_notes }}</p>
                    <p class="mt-1 text-xs text-gray-400">Submitted {{ $task->completed_at?->format('d M Y, h:i A') }}</p>

                    @if ($task->getMedia('proof')->isNotEmpty())
                        <div class="mt-3 grid grid-cols-3 gap-2 sm:grid-cols-4">
                            @foreach ($task->getMedia('proof') as $item)
                                <a href="{{ $item->getUrl() }}" target="_blank" class="block aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                                    @if (str_starts_with($item->mime_type, 'image'))
                                        <img src="{{ $item->getUrl() }}" class="h-full w-full object-cover">
                                    @else
                                        <div class="flex h-full w-full items-center justify-center"><x-icon name="file-text" class="h-6 w-6 text-gray-400" /></div>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($task->status === 'verified')
                        <div class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-700 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-300">
                            Verified by {{ $task->verifiedBy?->name }} on {{ $task->verified_at?->format('d M Y, h:i A') }}.
                        </div>
                    @endif
                </x-card>
            @endif

            @if ($task->delay_reason)
                <div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-2.5 text-sm text-rose-700 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-300">
                    <span class="font-semibold">Delay reason</span> (reported {{ $task->delay_reported_at?->format('d M Y') }}): {{ $task->delay_reason }}
                </div>
            @endif

            @if ($task->retasks->isNotEmpty())
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Retasked History</h3>
                    @foreach ($task->retasks as $retask)
                        <a href="{{ route('tasks.show', $retask) }}" class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 hover:text-indigo-600 dark:border-gray-800">
                            <span>Reassigned to {{ $retask->assignedTo?->name }} &middot; due {{ $retask->due_date->format('d M Y') }}</span>
                            <x-badge :status="$retask->status" />
                        </a>
                    @endforeach
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($isAssignee && in_array($task->status, ['pending'], true))
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Mark Completed</h3>
                    <form method="POST" action="{{ route('tasks.complete', $task) }}" enctype="multipart/form-data" class="space-y-3">
                        @csrf
                        <x-textarea-input name="completion_notes" rows="3" class="w-full" placeholder="What was done?" required></x-textarea-input>
                        <div>
                            <x-input-label value="Proof (images/documents, optional, multiple allowed)" />
                            <input type="file" name="proof[]" multiple class="mt-1 w-full text-sm">
                        </div>
                        <x-primary-button class="w-full justify-center">Mark Completed</x-primary-button>
                    </form>
                </x-card>

                @if ($task->isOverdue())
                    <x-card>
                        <h3 class="mb-3 text-sm font-semibold text-rose-600">Report Delay</h3>
                        <form method="POST" action="{{ route('tasks.delay', $task) }}" class="space-y-3">
                            @csrf
                            <x-textarea-input name="delay_reason" rows="2" class="w-full" placeholder="Why is this task delayed?" required>{{ $task->delay_reason }}</x-textarea-input>
                            <button class="w-full rounded-lg border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Submit Delay Reason</button>
                        </form>
                    </x-card>
                @endif
            @endif

            @if (($isVerifier || $isAdmin) && $task->status === 'submitted')
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Verify Submission</h3>
                    <form method="POST" action="{{ route('tasks.verify', $task) }}" class="mb-4">
                        @csrf
                        <x-primary-button class="w-full justify-center">Mark Completed (Verified)</x-primary-button>
                    </form>

                    <details>
                        <summary class="cursor-pointer text-xs font-medium text-rose-600">Not satisfactory — Retask</summary>
                        <form method="POST" action="{{ route('tasks.retask', $task) }}" class="mt-2 space-y-2">
                            @csrf
                            <x-select-input name="assigned_to" class="w-full text-sm" required>
                                @foreach (\App\Models\User::where('is_active', true)->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Client'))->orderBy('name')->get() as $user)
                                    <option value="{{ $user->id }}" @selected($user->id === $task->assigned_to)>{{ $user->name }}</option>
                                @endforeach
                            </x-select-input>
                            <x-text-input type="date" name="due_date" class="w-full text-sm" value="{{ now()->addDay()->format('Y-m-d') }}" required />
                            <x-textarea-input name="retask_note" rows="2" class="w-full text-sm" placeholder="What needs to be corrected?" required></x-textarea-input>
                            <button class="w-full rounded-lg bg-rose-600 px-3 py-2 text-sm font-semibold text-white hover:bg-rose-500">Retask</button>
                        </form>
                    </details>
                </x-card>
            @endif
        </div>
    </div>

    <div class="mt-6">
        <x-discussion-card :conversation="$discussion" />
    </div>
</x-app-layout>
