<div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
    <x-card class="lg:col-span-2">
        <h3 class="mb-4 text-sm font-semibold text-gray-500">Scope of Work</h3>
        <p class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $workOrder->scope ?: 'No scope defined.' }}</p>

        <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 text-sm dark:border-gray-800 sm:grid-cols-4">
            <div><dt class="text-gray-400">Client</dt><dd class="text-gray-800 dark:text-gray-200">{{ $workOrder->client?->name ?? 'Unknown client' }}</dd></div>
            <div><dt class="text-gray-400">Priority</dt><dd><x-badge :status="$workOrder->priority" /></dd></div>
            <div><dt class="text-gray-400">Deadline</dt><dd class="text-gray-800 dark:text-gray-200">{{ optional($workOrder->deadline)->format('d M Y') ?? '—' }}</dd></div>
            <div><dt class="text-gray-400">Budget</dt><dd class="text-gray-800 dark:text-gray-200">₹{{ number_format($workOrder->budget_amount ?? 0, 2) }}</dd></div>
            <div class="col-span-2"><dt class="text-gray-400">Execution Method</dt><dd class="text-gray-800 dark:text-gray-200">{{ \App\Models\WorkOrder::EXECUTION_WAYS[$workOrder->execution_way] ?? '—' }}</dd></div>
        </dl>

        @php
            $budgetBreakdown = collect([
                'Material' => $workOrder->estimated_material_budget,
                'Man Power' => $workOrder->estimated_labour_budget,
                'Equipment / Machinery' => $workOrder->estimated_equipment_budget,
                'Transport' => $workOrder->estimated_transport_budget,
                'Miscellaneous / Contingency' => $workOrder->estimated_misc_budget,
            ])->filter(fn ($amount) => (float) ($amount ?? 0) > 0);
        @endphp
        @if ($budgetBreakdown->isNotEmpty())
            <dl class="mt-2 flex flex-wrap gap-6 border-t border-gray-100 pt-4 text-sm dark:border-gray-800">
                @foreach ($budgetBreakdown as $label => $amount)
                    <div><dt class="text-gray-400">{{ $label }}</dt><dd class="font-medium text-gray-800 dark:text-gray-200">₹{{ number_format($amount, 2) }}</dd></div>
                @endforeach
            </dl>
        @endif

        @php
            $budgetItemsByCategory = $workOrder->budgetItems->groupBy('category');
            $budgetCategoryLabels = [
                'material' => 'Material',
                'labour' => 'Man Power',
                'equipment' => 'Equipment / Machinery',
                'transport' => 'Transport',
                'misc' => 'Miscellaneous / Contingency',
            ];
        @endphp
        @if ($budgetItemsByCategory->isNotEmpty() || $workOrder->timeSchedules->isNotEmpty())
            <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                <h4 class="mb-3 text-sm font-semibold text-gray-500">Planned Budget Details</h4>

                @foreach ($budgetCategoryLabels as $category => $label)
                    @if ($budgetItemsByCategory->has($category))
                        <div class="mb-4">
                            <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">{{ $label }}</p>
                            <div class="overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
                                <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                                    <thead>
                                        <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                                            <th class="px-3 py-2">{{ $category === 'labour' ? 'Designation' : 'Item' }}</th>
                                            @if ($category === 'material')
                                                <th class="px-3 py-2">Brand</th>
                                                <th class="px-3 py-2">Size</th>
                                            @endif
                                            @if ($category !== 'labour')
                                                <th class="px-3 py-2">Unit</th>
                                            @endif
                                            <th class="px-3 py-2 text-right">{{ $category === 'labour' ? 'Count' : 'Qty' }}</th>
                                            @if ($category === 'labour')
                                                <th class="px-3 py-2 text-right">Hours</th>
                                            @endif
                                            <th class="px-3 py-2 text-right">{{ $category === 'labour' ? 'Wage Rate' : 'Rate' }}</th>
                                            @if ($category !== 'labour')
                                                <th class="px-3 py-2">Vendor</th>
                                            @endif
                                            <th class="px-3 py-2 text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                        @foreach ($budgetItemsByCategory[$category] as $item)
                                            <tr>
                                                <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $item->name }}</td>
                                                @if ($category === 'material')
                                                    <td class="px-3 py-2 text-gray-500">{{ $item->brand ?? '—' }}</td>
                                                    <td class="px-3 py-2 text-gray-500">{{ $item->size ?? '—' }}</td>
                                                @endif
                                                @if ($category !== 'labour')
                                                    <td class="px-3 py-2 text-gray-500">{{ $item->unit ?? '—' }}</td>
                                                @endif
                                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">{{ $item->quantity }}</td>
                                                @if ($category === 'labour')
                                                    <td class="px-3 py-2 text-right text-gray-500">{{ $item->hours ?? '—' }}</td>
                                                @endif
                                                <td class="px-3 py-2 text-right text-gray-700 dark:text-gray-300">₹{{ number_format($item->rate ?? 0, 2) }}</td>
                                                @if ($category !== 'labour')
                                                    <td class="px-3 py-2 text-gray-500">{{ $item->vendor ?? '—' }}</td>
                                                @endif
                                                <td class="px-3 py-2 text-right font-medium text-gray-900 dark:text-white">₹{{ number_format($item->amount ?? 0, 2) }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endforeach

                @if ($workOrder->timeSchedules->isNotEmpty())
                    <div>
                        <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-gray-400">Time Schedule</p>
                        <div class="overflow-x-auto rounded-lg border border-gray-100 dark:border-gray-800">
                            <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                                <thead>
                                    <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                                        <th class="px-3 py-2">Time to Finish</th>
                                        <th class="px-3 py-2">Unit</th>
                                        <th class="px-3 py-2">Remark</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($workOrder->timeSchedules as $schedule)
                                        <tr>
                                            <td class="px-3 py-2 text-gray-700 dark:text-gray-300">{{ $schedule->time_to_finish }}</td>
                                            <td class="px-3 py-2 text-gray-500">{{ $schedule->unit ?? '—' }}</td>
                                            <td class="px-3 py-2 text-gray-500">{{ $schedule->remark ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        @endif

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

        @if (in_array($workOrder->status, ['in_progress', 'rework_in_progress']))
            <div class="mt-6 border-t border-gray-100 pt-4 dark:border-gray-800">
                @can('daily_progress.manage')
                    <form method="POST" action="{{ route('work-orders.submit-for-qc', $workOrder) }}" onsubmit="return confirm('Mark this work as completed and submit it for QC?')">
                        @csrf
                        <x-primary-button>Work Completed — Submit for QC</x-primary-button>
                    </form>
                @endcan
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
                    <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                        <x-link-button :href="route('work-orders.create', ['quotation_id' => $workOrder->quotation_id, 'parent_work_order_id' => $workOrder->id])">Create Next Work Order</x-link-button>
                    </div>
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
