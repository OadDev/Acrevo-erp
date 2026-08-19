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

            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Progress Updates</h3>
                @forelse ($workOrder->dailyProgressReports as $report)
                    <div class="border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                        <p class="font-medium text-gray-800 dark:text-gray-200">{{ $report->date->format('d M Y') }}</p>
                        <p class="text-gray-600 dark:text-gray-300">{{ $report->completed_work }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No updates yet.</p>
                @endforelse
            </x-card>

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
        </div>

        <div class="space-y-6">
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
                        @if ($approval->getFirstMedia('attachment'))
                            <a href="{{ $approval->getFirstMediaUrl('attachment') }}" target="_blank" class="mt-1 inline-flex items-center gap-1 text-indigo-600 hover:underline">
                                <x-icon name="paperclip" class="h-3.5 w-3.5" /> {{ $approval->getFirstMedia('attachment')->file_name }}
                            </a>
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
                    </div>
                @empty
                    <p class="text-sm text-gray-400">No approval requests yet.</p>
                @endforelse

                <form method="POST" action="{{ route('portal.work-orders.approval-requests.store', $workOrder) }}" enctype="multipart/form-data" class="mt-4 space-y-2 border-t border-gray-100 pt-4 dark:border-gray-800">
                    @csrf
                    <x-input-label value="Request Our Approval" />
                    <x-text-input name="title" placeholder="Title" class="w-full" required />
                    <x-textarea-input name="description" rows="2" class="w-full" placeholder="Description (optional)"></x-textarea-input>
                    <input type="file" name="file" accept=".jpg,.jpeg,.png,.pdf,.doc,.docx" class="w-full text-sm">
                    <x-primary-button class="w-full justify-center">Send Request</x-primary-button>
                </form>
            </x-card>

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
        </div>
    </div>
</x-app-layout>
