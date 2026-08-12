<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <x-card class="lg:col-span-2">
        <h3 class="mb-4 text-sm font-semibold text-gray-500">Scope of Work</h3>
        <p class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $workOrder->scope ?: 'No scope defined.' }}</p>

        <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 text-sm dark:border-gray-800 sm:grid-cols-4">
            <div><dt class="text-gray-400">Client</dt><dd class="text-gray-800 dark:text-gray-200">{{ $workOrder->client->name }}</dd></div>
            <div><dt class="text-gray-400">Priority</dt><dd><x-badge :status="$workOrder->priority" /></dd></div>
            <div><dt class="text-gray-400">Deadline</dt><dd class="text-gray-800 dark:text-gray-200">{{ optional($workOrder->deadline)->format('d M Y') ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Budget</dt><dd class="text-gray-800 dark:text-gray-200">₹{{ number_format($workOrder->budget_amount ?? 0, 2) }}</dd></div>
            <div class="col-span-2"><dt class="text-gray-400">Execution Method</dt><dd class="text-gray-800 dark:text-gray-200">{{ \App\Models\WorkOrder::EXECUTION_WAYS[$workOrder->execution_way] ?? '—' }}</dd></div>
        </dl>

        @if ($workOrder->parent)
            <p class="mt-4 text-sm text-gray-500">Follows: <a href="{{ route('work-orders.show', $workOrder->parent) }}" class="text-indigo-600 hover:underline">{{ $workOrder->parent->work_order_no }}</a></p>
        @endif
        @if ($workOrder->children->isNotEmpty())
            <div class="mt-4">
                <p class="text-sm text-gray-500">Follow-up work orders:</p>
                @foreach ($workOrder->children as $child)
                    <a href="{{ route('work-orders.show', $child) }}" class="mr-3 text-sm text-indigo-600 hover:underline">{{ $child->work_order_no }}</a>
                @endforeach
            </div>
        @endif

        @if ($workOrder->status === 'client_review')
            <div class="mt-6 border-t border-gray-100 pt-4 dark:border-gray-800">
                <h4 class="mb-2 text-sm font-semibold text-gray-500">Client Actions</h4>
                <div class="flex flex-wrap gap-2">
                    @can('tickets.create')
                        <x-link-button :href="route('tickets.create', ['work_order_id' => $workOrder->id])" variant="secondary">Raise a Ticket</x-link-button>
                    @endcan
                    @can('work_orders.edit')
                        <form method="POST" action="{{ route('work-orders.complete', $workOrder) }}" class="inline">
                            @csrf
                            <x-primary-button>Client Confirmed — Complete</x-primary-button>
                        </form>
                    @endcan
                </div>
            </div>
        @endif

        @if ($workOrder->status === 'completed')
            <div class="mt-6 border-t border-gray-100 pt-4 dark:border-gray-800">
                <h4 class="mb-2 text-sm font-semibold text-gray-500">Client Feedback</h4>
                @forelse ($workOrder->clientReviews as $review)
                    <div class="mb-2 flex items-center gap-2 text-sm">
                        <span class="font-medium">{{ $review->rating }}/5</span>
                        <span class="text-gray-500">{{ $review->comments }}</span>
                    </div>
                @empty
                    <form method="POST" action="{{ route('work-orders.feedback', $workOrder) }}" class="flex items-end gap-2">
                        @csrf
                        <div>
                            <x-input-label value="Rating (1-5)" />
                            <x-text-input type="number" name="rating" min="1" max="5" class="mt-1 w-24" required />
                        </div>
                        <div class="flex-1">
                            <x-input-label value="Comments" />
                            <x-text-input name="comments" class="mt-1 w-full" />
                        </div>
                        <x-primary-button>Save</x-primary-button>
                    </form>
                @endforelse

                @can('work_orders.create')
                    <form method="POST" action="{{ route('work-orders.next', $workOrder) }}" class="mt-4 space-y-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                        @csrf
                        <x-input-label value="Start a Next Work Order for this client" />
                        <x-text-input name="title" placeholder="Title" class="w-full" required />
                        <x-primary-button>Create Next Work Order</x-primary-button>
                    </form>
                @endcan
            </div>
        @endif
    </x-card>

    <x-card>
        <h3 class="mb-4 text-sm font-semibold text-gray-500">Status Timeline</h3>
        <ol class="space-y-4 border-l border-gray-200 pl-4 dark:border-gray-800">
            @foreach ($workOrder->statusLogs as $log)
                <li>
                    <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ Str::title(str_replace('_',' ',$log->to_status)) }}</p>
                    <p class="text-xs text-gray-400">{{ $log->changedBy?->name ?? 'System' }} · {{ $log->changed_at->diffForHumans() }}</p>
                    @if ($log->remarks)
                        <p class="text-xs text-gray-500">{{ $log->remarks }}</p>
                    @endif
                </li>
            @endforeach
        </ol>
    </x-card>
</div>
