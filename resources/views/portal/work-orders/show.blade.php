<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$workOrder->title" :subtitle="$workOrder->work_order_no">
            <x-slot name="actions">
                <x-badge :status="$workOrder->status" class="text-sm" />
                <x-link-button :href="route('portal.tickets.index', ['work_order_id' => $workOrder->id])" variant="secondary">Raise a Ticket</x-link-button>
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($workOrder->client->canViewSection('overview'))
                <x-card>
                    <h3 class="mb-4 text-sm font-semibold text-gray-500">Overview</h3>
                    <p class="whitespace-pre-line text-sm text-gray-700 dark:text-gray-300">{{ $workOrder->scope ?: 'No scope defined.' }}</p>

                    <dl class="mt-4 grid grid-cols-2 gap-4 border-t border-gray-100 pt-4 text-sm dark:border-gray-800 sm:grid-cols-4">
                        <div><dt class="text-gray-400">Priority</dt><dd><x-badge :status="$workOrder->priority" /></dd></div>
                        <div><dt class="text-gray-400">Deadline</dt><dd class="text-gray-800 dark:text-gray-200">{{ optional($workOrder->deadline)->format('d M Y') ?? '—' }}</dd></div>
                        <div><dt class="text-gray-400">Budget</dt><dd class="text-gray-800 dark:text-gray-200">₹{{ number_format($workOrder->budget_amount ?? 0, 2) }}</dd></div>
                        <div><dt class="text-gray-400">Execution Method</dt><dd class="text-gray-800 dark:text-gray-200">{{ \App\Models\WorkOrder::EXECUTION_WAYS[$workOrder->execution_way] ?? '—' }}</dd></div>
                    </dl>
                </x-card>
            @endif

            @if ($workOrder->client->canViewSection('media'))
                <x-card>
                    <h3 class="mb-4 text-sm font-semibold text-gray-500">Progress Photos &amp; Videos</h3>
                    <div class="grid grid-cols-3 gap-2 sm:grid-cols-4">
                        @forelse ($workOrder->media as $item)
                            <a href="{{ $item->getUrl() }}" target="_blank" class="block aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                                @if (str_starts_with($item->mime_type, 'image'))
                                    <img src="{{ $item->getUrl() }}" class="h-full w-full object-cover">
                                @else
                                    <div class="flex h-full w-full items-center justify-center"><x-icon name="video" class="h-6 w-6 text-gray-400" /></div>
                                @endif
                            </a>
                        @empty
                            <p class="col-span-full text-sm text-gray-400">No media shared yet.</p>
                        @endforelse
                    </div>
                </x-card>
            @endif

            @if ($workOrder->client->canViewSection('checklist'))
                <x-card>
                    <h3 class="mb-4 text-sm font-semibold text-gray-500">Daily Work &amp; Checklist</h3>
                    @forelse ($workOrder->dailyChecklists as $checklist)
                        <div class="border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                            <p class="font-medium text-gray-800 dark:text-gray-200">{{ $checklist->title ?? 'Daily Work' }} <span class="font-normal text-gray-400">— {{ $checklist->date->format('d M Y') }}</span></p>
                            <ul class="mt-2 space-y-2">
                                @foreach ($checklist->checklistItems as $item)
                                    <li class="flex items-center gap-2">
                                        <x-icon :name="$item->is_done ? 'check-circle' : 'clock'" class="h-4 w-4 shrink-0 {{ $item->is_done ? 'text-emerald-500' : 'text-amber-500' }}" />
                                        <span class="flex-1 text-gray-600 dark:text-gray-300 {{ $item->is_done ? 'line-through decoration-gray-300' : '' }}">{{ $item->description }}</span>
                                        @if ($item->is_done && $item->getFirstMedia('proof'))
                                            <a href="{{ $item->getFirstMediaUrl('proof') }}" target="_blank" class="text-xs font-medium text-indigo-600 hover:underline">View Proof</a>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No daily work entries yet.</p>
                    @endforelse
                </x-card>
            @endif

            @if ($workOrder->client->canViewSection('progress'))
                <x-card>
                    <h3 class="mb-4 text-sm font-semibold text-gray-500">Progress Updates</h3>
                    @forelse ($workOrder->dailyProgressReports as $report)
                        @php
                            $images = $report->getMedia('attachments')->filter(fn ($m) => str_starts_with($m->mime_type, 'image'));
                            $documents = $report->getMedia('attachments')->reject(fn ($m) => str_starts_with($m->mime_type, 'image'));
                        @endphp
                        <div class="border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                            <p class="font-medium text-gray-800 dark:text-gray-200">{{ $report->date->format('d M Y') }}</p>
                            <p class="text-gray-600 dark:text-gray-300">{{ $report->completed_work }}</p>

                            @if ($images->isNotEmpty())
                                <div class="mt-2">
                                    <p class="text-xs font-medium text-gray-400">Related Images</p>
                                    <div class="mt-1 grid grid-cols-4 gap-2 sm:grid-cols-6">
                                        @foreach ($images as $item)
                                            <a href="{{ $item->getUrl() }}" target="_blank" class="block aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                                                <img src="{{ $item->getUrl() }}" class="h-full w-full object-cover">
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if ($documents->isNotEmpty())
                                <div class="mt-2">
                                    <p class="text-xs font-medium text-gray-400">Related Documents</p>
                                    <div class="mt-1 space-y-1">
                                        @foreach ($documents as $item)
                                            <a href="{{ $item->getUrl() }}" target="_blank" class="flex items-center gap-1.5 text-xs font-medium text-indigo-600 hover:underline">
                                                <x-icon name="file-text" class="h-3.5 w-3.5 shrink-0" /> {{ $item->file_name }}
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No updates yet.</p>
                    @endforelse
                </x-card>
            @endif

            @if ($workOrder->client->canViewSection('summary'))
                <x-card :padded="false">
                    <h3 class="p-4 pb-0 text-sm font-semibold text-gray-500">Monthly Summary</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2">Date</th>
                                    <th class="px-4 py-2">Work Done/Not</th>
                                    <th class="px-4 py-2">Responsibility</th>
                                    <th class="px-4 py-2">Work Detail/Reason</th>
                                    <th class="px-4 py-2 text-right">Days in Client Bear</th>
                                    <th class="px-4 py-2 text-right">Remaining Construction Days</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($workOrder->summaries as $entry)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->entry_date->format('d M Y') }}</td>
                                        <td class="px-4 py-2">
                                            <span class="{{ $entry->status === 'done' ? 'text-emerald-600' : 'text-gray-500' }}">{{ $entry->status === 'done' ? 'Done' : 'No' }}</span>
                                        </td>
                                        <td class="px-4 py-2 text-gray-500">{{ ucfirst($entry->responsibility) }}</td>
                                        <td class="px-4 py-2">{{ $entry->work_detail ?? '—' }}</td>
                                        <td class="px-4 py-2 text-right text-gray-500">{{ $entry->client_bear_days ?? '—' }}</td>
                                        <td class="px-4 py-2 text-right text-gray-500">{{ $entry->remaining_construction_days ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No summary entries yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            @if ($workOrder->client->canViewSection('ledger'))
                <x-card :padded="false">
                    <h3 class="p-4 pb-0 text-sm font-semibold text-gray-500">Site Ledger</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2">Date</th>
                                    <th class="px-4 py-2">Category</th>
                                    <th class="px-4 py-2">Description</th>
                                    <th class="px-4 py-2">Type</th>
                                    <th class="px-4 py-2 text-right">Amount</th>
                                    <th class="px-4 py-2 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($workOrder->ledgers as $entry)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->entry_date->format('d M Y') }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->category ?? '—' }}</td>
                                        <td class="px-4 py-2">{{ $entry->description ?? '—' }}</td>
                                        <td class="px-4 py-2"><x-badge :status="$entry->type" /></td>
                                        <td class="px-4 py-2 text-right">₹{{ number_format($entry->amount, 2) }}</td>
                                        <td class="px-4 py-2 text-right font-medium text-gray-800 dark:text-gray-200">₹{{ number_format($entry->balance, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No ledger entries yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            @if ($workOrder->client->canViewSection('mb'))
                <x-card>
                    <h3 class="mb-4 text-sm font-semibold text-gray-500">Measurement Book</h3>
                    @forelse ($workOrder->measurementBooks as $mb)
                        <div class="border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                            <p class="font-medium text-gray-800 dark:text-gray-200">{{ $mb->mb_no }} — {{ $mb->date->format('d M Y') }}</p>
                            @if ($mb->items->isNotEmpty())
                                <div class="mt-2 overflow-x-auto">
                                    <table class="min-w-full divide-y divide-gray-100 text-xs dark:divide-gray-800">
                                        <thead>
                                            <tr class="text-left text-gray-400">
                                                <th class="py-1 pr-2">Work Description</th>
                                                <th class="py-1 pr-2">L</th>
                                                <th class="py-1 pr-2">B</th>
                                                <th class="py-1 pr-2">D</th>
                                                <th class="py-1 pr-2">Total</th>
                                                <th class="py-1 pr-2">Unit</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                            @foreach ($mb->items as $item)
                                                <tr>
                                                    <td class="py-1 pr-2">{{ $item->item_description }}</td>
                                                    <td class="py-1 pr-2">{{ $item->length ?? '—' }}</td>
                                                    <td class="py-1 pr-2">{{ $item->breadth ?? '—' }}</td>
                                                    <td class="py-1 pr-2">{{ $item->height ?? '—' }}</td>
                                                    <td class="py-1 pr-2">{{ $item->quantity }}</td>
                                                    <td class="py-1 pr-2">{{ $item->unit }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No measurement book entries yet.</p>
                    @endforelse
                </x-card>
            @endif

            @if ($workOrder->client->canViewSection('materials'))
                <x-card :padded="false">
                    <h3 class="p-4 pb-0 text-sm font-semibold text-gray-500">Material Inward</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2">Date</th>
                                    <th class="px-4 py-2">Material Description</th>
                                    <th class="px-4 py-2">Nos</th>
                                    <th class="px-4 py-2">Unit</th>
                                    <th class="px-4 py-2 text-right">Rate/Unit</th>
                                    <th class="px-4 py-2 text-right">Total Rate</th>
                                    <th class="px-4 py-2">Supplier Details</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($workOrder->materialEntries as $entry)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->entry_date?->format('d M Y') ?? '—' }}</td>
                                        <td class="px-4 py-2">{{ $entry->material_name }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->quantity }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->unit }}</td>
                                        <td class="px-4 py-2 text-right text-gray-500">₹{{ number_format($entry->rate, 2) }}</td>
                                        <td class="px-4 py-2 text-right font-medium">₹{{ number_format($entry->amount, 2) }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->vendor ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="7" class="px-4 py-6 text-center text-gray-400">No material inward entries yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            @if ($workOrder->client->canViewSection('material_usage'))
                <x-card :padded="false">
                    <h3 class="p-4 pb-0 text-sm font-semibold text-gray-500">Used Material</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2">Date</th>
                                    <th class="px-4 py-2">Material Name</th>
                                    <th class="px-4 py-2">Nos</th>
                                    <th class="px-4 py-2">Unit</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($workOrder->materialUsageEntries as $entry)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->date->format('d M Y') }}</td>
                                        <td class="px-4 py-2">{{ $entry->material_name }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->quantity }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->unit }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="px-4 py-6 text-center text-gray-400">No used material entries yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            @if ($workOrder->client->canViewSection('manpower'))
                <x-card :padded="false">
                    <h3 class="p-4 pb-0 text-sm font-semibold text-gray-500">Used Manpower</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2">Date</th>
                                    <th class="px-4 py-2">Designation</th>
                                    <th class="px-4 py-2">Nos</th>
                                    <th class="px-4 py-2">Target Hrs</th>
                                    <th class="px-4 py-2">Actual Time Taken</th>
                                    <th class="px-4 py-2">Remark</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($workOrder->labourEntries as $entry)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->entry_date?->format('d M Y') ?? '—' }}</td>
                                        <td class="px-4 py-2">{{ $entry->labour_type }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->count }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->hours ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->total_time_to_finish ?? '—' }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->remark ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No used manpower entries yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            @if ($workOrder->client->canViewSection('company_ledger'))
                <x-card :padded="false">
                    <h3 class="p-4 pb-0 text-sm font-semibold text-gray-500">Company Ledger</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-gray-400">
                                    <th class="px-4 py-2">Date</th>
                                    <th class="px-4 py-2">Category</th>
                                    <th class="px-4 py-2">Description</th>
                                    <th class="px-4 py-2">Type</th>
                                    <th class="px-4 py-2 text-right">Amount</th>
                                    <th class="px-4 py-2 text-right">Balance</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($workOrder->companyLedgers as $entry)
                                    <tr>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->entry_date->format('d M Y') }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $entry->category ?? '—' }}</td>
                                        <td class="px-4 py-2">{{ $entry->description ?? '—' }}</td>
                                        <td class="px-4 py-2"><x-badge :status="$entry->type" /></td>
                                        <td class="px-4 py-2 text-right">₹{{ number_format($entry->amount, 2) }}</td>
                                        <td class="px-4 py-2 text-right font-medium text-gray-800 dark:text-gray-200">₹{{ number_format($entry->balance, 2) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No company ledger entries yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endif

            @if ($workOrder->client->canViewSection('qc'))
                <x-card>
                    <h3 class="mb-4 text-sm font-semibold text-gray-500">QC</h3>
                    @forelse ($workOrder->qcInspections as $inspection)
                        <div class="border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                            <div class="flex items-center justify-between gap-2">
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ Str::title($inspection->inspection_type) }} QC — {{ $inspection->inspection_date->format('d M Y') }}</span>
                                <x-badge :status="$inspection->status" />
                            </div>
                            @if ($inspection->remarks)
                                <p class="mt-1 text-gray-500">{{ $inspection->remarks }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No QC inspections yet.</p>
                    @endforelse
                </x-card>
            @endif
        </div>

        <div class="space-y-6">
            @if ($workOrder->client->canViewSection('overview'))
                <x-card>
                    <h3 class="mb-4 text-sm font-semibold text-gray-500">Status Timeline</h3>
                    <ol class="space-y-4 border-l border-gray-200 pl-4 dark:border-gray-800">
                        @foreach ($workOrder->statusLogs as $log)
                            <li>
                                <p class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ Str::title(str_replace('_',' ',$log->to_status)) }}</p>
                                <p class="text-xs text-gray-400">{{ $log->changed_at->diffForHumans() }}</p>
                            </li>
                        @endforeach
                    </ol>
                </x-card>
            @endif

            @if ($workOrder->status === 'client_review')
                <x-card>
                    <h3 class="mb-2 text-sm font-semibold text-emerald-600 dark:text-emerald-400">QC Passed — Your Review Needed</h3>
                    <p class="mb-4 text-sm text-gray-600 dark:text-gray-300">Our quality team has signed off on this work. Please review it and confirm.</p>
                    <div class="flex flex-wrap gap-2">
                        <form method="POST" action="{{ route('portal.work-orders.accept', $workOrder) }}">
                            @csrf
                            <x-primary-button>Accept &amp; Complete</x-primary-button>
                        </form>
                        <x-link-button :href="route('portal.tickets.index', ['work_order_id' => $workOrder->id])" variant="secondary">Raise a Ticket Instead</x-link-button>
                    </div>
                </x-card>
            @endif

            @if ($workOrder->status === 'completed')
                <x-card>
                    <h3 class="mb-3 text-sm font-semibold text-gray-500">Your Feedback</h3>
                    @if ($workOrder->clientReviews->isEmpty())
                        <form method="POST" action="{{ route('work-orders.feedback', $workOrder) }}" class="space-y-2">
                            @csrf
                            <x-input-label value="Rating (1-5)" />
                            <x-text-input type="number" name="rating" min="1" max="5" class="w-full" required />
                            <x-textarea-input name="comments" rows="2" class="w-full" placeholder="Comments"></x-textarea-input>
                            <x-primary-button class="w-full justify-center">Submit Feedback</x-primary-button>
                        </form>
                    @else
                        <p class="text-sm text-gray-600 dark:text-gray-300">Thank you for your feedback!</p>
                    @endif
                </x-card>
            @endif

            @if ($workOrder->client->canViewSection('approvals'))
            <x-card>
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-gray-500">Approval Requests</h3>
                    @if ($workOrder->approvalRequests->where('status', 'approved')->isNotEmpty())
                        <a href="{{ route('portal.work-orders.approval-requests.approved-pdf', $workOrder) }}" class="inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline">
                            <x-icon name="download" class="h-3.5 w-3.5" /> All Approved (PDF)
                        </a>
                    @endif
                </div>
                @forelse ($workOrder->approvalRequests as $approval)
                    <div class="border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ $approval->title }}</span>
                            <x-badge :status="$approval->status" />
                        </div>
                        <dl class="mt-1 grid grid-cols-1 gap-x-4 gap-y-0.5 text-xs text-gray-400 sm:grid-cols-3">
                            <div><dt class="inline text-gray-400">Raised By:</dt> <dd class="inline text-gray-600 dark:text-gray-300">{{ $approval->raisedByName() }}</dd></div>
                            <div><dt class="inline text-gray-400">Sent To:</dt> <dd class="inline text-gray-600 dark:text-gray-300">{{ $approval->sentToName() }}</dd></div>
                            <div><dt class="inline text-gray-400">Requested:</dt> <dd class="inline text-gray-600 dark:text-gray-300">{{ $approval->requestedAtIst() }}</dd></div>
                        </dl>
                        @if ($approval->description)
                            <p class="mt-1 text-gray-500">{{ $approval->description }}</p>
                        @endif
                        @if ($approval->getMedia('attachment')->isNotEmpty())
                            <div class="mt-1 flex flex-wrap gap-2">
                                @foreach ($approval->getMedia('attachment') as $attachment)
                                    <a href="{{ $attachment->getUrl() }}" target="_blank" class="inline-flex items-center gap-1 text-indigo-600 hover:underline">
                                        <x-icon name="paperclip" class="h-3.5 w-3.5" /> {{ $attachment->file_name }}
                                    </a>
                                @endforeach
                            </div>
                        @endif

                        @if ($approval->status !== 'pending')
                            <div class="mt-2 rounded-lg border border-gray-100 bg-gray-50 px-3 py-2 text-xs dark:border-gray-800 dark:bg-gray-900">
                                <p class="font-medium text-gray-700 dark:text-gray-300">{{ Str::title($approval->status) }} by {{ $approval->respondedBy?->name ?? '—' }} on {{ $approval->respondedAtIst() }}</p>
                                @if ($approval->response_note)
                                    <p class="text-gray-500">{{ $approval->response_note }}</p>
                                @endif
                            </div>
                        @elseif ($approval->direction === 'company_to_client')
                            <form method="POST" action="{{ route('portal.work-orders.approval-requests.respond', [$workOrder, $approval]) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                @csrf
                                <x-text-input name="response_note" placeholder="Note (optional)" class="flex-1" />
                                <button name="status" value="approved" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Approve</button>
                                <button name="status" value="rejected" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Reject</button>
                            </form>
                        @else
                            <p class="mt-1 text-xs text-gray-400">Awaiting our team's response.</p>
                        @endif

                        <a href="{{ route('portal.work-orders.approval-requests.pdf', [$workOrder, $approval]) }}" class="mt-2 inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline">
                            <x-icon name="download" class="h-3.5 w-3.5" /> Download PDF
                        </a>
                        @if ($approval->getMedia('attachment')->isNotEmpty())
                            <a href="{{ route('portal.work-orders.approval-requests.zip', [$workOrder, $approval]) }}" class="mt-2 ml-3 inline-flex items-center gap-1 text-xs font-medium text-indigo-600 hover:underline">
                                <x-icon name="download" class="h-3.5 w-3.5" /> Download Attachments (ZIP)
                            </a>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No approval requests yet.</p>
                @endforelse

                <form method="POST" action="{{ route('portal.work-orders.approval-requests.store', $workOrder) }}" enctype="multipart/form-data" class="mt-4 space-y-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                    @csrf
                    <x-input-label value="Request Our Approval" />
                    <x-text-input name="title" placeholder="Title" class="w-full" required />
                    <x-textarea-input name="description" rows="2" class="w-full" placeholder="Description (optional)"></x-textarea-input>
                    <input type="file" name="files[]" multiple accept=".jpg,.jpeg,.png,.pdf,.doc,.docx,.mp4,.mov,.avi" class="w-full text-sm">
                    <x-primary-button class="w-full justify-center">Send Request</x-primary-button>
                </form>
            </x-card>
            @endif

            @if ($workOrder->client->canViewSection('tickets'))
            <x-card>
                <h3 class="mb-3 text-sm font-semibold text-gray-500">Tickets</h3>
                @forelse ($workOrder->tickets as $ticket)
                    <div class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <span class="text-gray-700 dark:text-gray-300">{{ $ticket->title }}</span>
                        <x-badge :status="$ticket->status" />
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No tickets raised.</p>
                @endforelse
            </x-card>
            @endif

            @if ($discussion)
            <x-discussion-card
                :conversation="$discussion"
                title="Discussion"
                subtitle="Message our team about this work order."
                :store-url="route('portal.work-orders.discussion.messages.store', $workOrder)"
                :poll-url="route('portal.work-orders.discussion.poll', $workOrder)"
            />
            @endif
        </div>
    </div>
</x-app-layout>
