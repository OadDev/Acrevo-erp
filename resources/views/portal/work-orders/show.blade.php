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
                        <div class="aspect-square overflow-hidden rounded-lg bg-gray-100 dark:bg-gray-800">
                            @if (str_starts_with($item->mime_type, 'image'))
                                <img src="{{ $item->getUrl() }}" class="h-full w-full object-cover">
                            @else
                                <div class="flex h-full w-full items-center justify-center"><x-icon name="video" class="h-6 w-6 text-gray-400" /></div>
                            @endif
                        </div>
                    @empty
                        <p class="col-span-full text-sm text-gray-400">No media shared yet.</p>
                    @endforelse
                </div>
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
                <h3 class="mb-3 text-sm font-semibold text-gray-500">Approval Requests</h3>
                @forelse ($workOrder->approvalRequests as $approval)
                    <div class="border-b border-gray-100 py-3 text-sm last:border-0 dark:border-gray-800">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium text-gray-800 dark:text-gray-200">{{ $approval->title }}</span>
                            <x-badge :status="$approval->status" />
                        </div>
                        @if ($approval->description)
                            <p class="mt-1 text-gray-500">{{ $approval->description }}</p>
                        @endif
                        @if ($approval->getFirstMedia('attachment'))
                            <a href="{{ $approval->getFirstMediaUrl('attachment') }}" target="_blank" class="mt-1 inline-flex items-center gap-1 text-indigo-600 hover:underline">
                                <x-icon name="paperclip" class="h-3.5 w-3.5" /> {{ $approval->getFirstMedia('attachment')->file_name }}
                            </a>
                        @endif

                        @if ($approval->status === 'pending' && $approval->direction === 'company_to_client')
                            <form method="POST" action="{{ route('portal.work-orders.approval-requests.respond', [$workOrder, $approval]) }}" class="mt-2 flex flex-wrap items-end gap-2">
                                @csrf
                                <x-text-input name="response_note" placeholder="Note (optional)" class="flex-1" />
                                <button name="status" value="approved" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-500">Approve</button>
                                <button name="status" value="rejected" class="rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Reject</button>
                            </form>
                        @elseif ($approval->status === 'pending')
                            <p class="mt-1 text-xs text-gray-400">Awaiting our team's response.</p>
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
