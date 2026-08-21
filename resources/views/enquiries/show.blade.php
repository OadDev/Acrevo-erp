<x-app-layout>
    <x-slot name="header">
        <x-page-header :title="$enquiry->enquiry_no" :subtitle="$enquiry->contact_name">
            <x-slot name="actions">
                <x-badge :status="$enquiry->status" class="text-sm" />
                @can('enquiries.edit')
                    <x-link-button :href="route('enquiries.edit', $enquiry)" variant="secondary">Edit</x-link-button>
                @endcan
                @can('site_visits.create')
                    <x-link-button :href="route('site-visits.create', ['enquiry_id' => $enquiry->id])">Schedule Site Visit</x-link-button>
                @endcan
                @can('quotations.create')
                    <x-link-button :href="route('quotations.create', ['enquiry_id' => $enquiry->id])">Create Quotation</x-link-button>
                @endcan
            </x-slot>
        </x-page-header>
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Requirement</h3>
                <dl class="grid grid-cols-2 gap-4 text-sm">
                    <div><dt class="text-gray-400">Service Type</dt><dd class="text-gray-800 dark:text-gray-200">{{ $enquiry->service_type ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400">Source</dt><dd class="text-gray-800 dark:text-gray-200">{{ Str::title(str_replace('_',' ',$enquiry->source)) }}</dd></div>
                    <div><dt class="text-gray-400">Assigned To</dt><dd class="text-gray-800 dark:text-gray-200">{{ $enquiry->assignedTo?->name ?? '—' }}</dd></div>
                    <div><dt class="text-gray-400">Next Follow-up</dt><dd class="text-gray-800 dark:text-gray-200">{{ optional($enquiry->follow_up_date)->format('d M Y') ?? '—' }}</dd></div>
                    <div class="col-span-2"><dt class="text-gray-400">Description</dt><dd class="text-gray-800 dark:text-gray-200">{{ $enquiry->description ?: '—' }}</dd></div>
                </dl>
            </x-card>

            <x-card>
                <h3 class="mb-4 text-sm font-semibold text-gray-500">Follow-up Log</h3>
                @can('enquiries.edit')
                    <form method="POST" action="{{ route('enquiries.follow-ups.store', $enquiry) }}" class="mb-4 space-y-2">
                        @csrf
                        <x-textarea-input name="note" rows="2" class="w-full" placeholder="Log a call, email, or update..." required></x-textarea-input>
                        <div class="flex items-center gap-2">
                            <x-text-input type="date" name="next_follow_up_date" class="text-sm" />
                            <x-primary-button type="submit">Log Follow-up</x-primary-button>
                        </div>
                    </form>
                @endcan
                <div class="space-y-3">
                    @forelse ($enquiry->followUps->sortByDesc('created_at') as $followUp)
                        <div class="border-l-2 border-indigo-200 pl-3 text-sm dark:border-indigo-500/30">
                            <p class="text-gray-700 dark:text-gray-300">{{ $followUp->note }}</p>
                            <p class="text-xs text-gray-400">{{ $followUp->user?->name }} · {{ $followUp->created_at->diffForHumans() }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-gray-400">No follow-ups logged yet.</p>
                    @endforelse
                </div>
            </x-card>

            <x-discussion-card :conversation="$discussion" />
        </div>

        <div class="space-y-6">
            <x-card>
                <h3 class="mb-3 text-sm font-semibold text-gray-500">Client</h3>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-gray-400">Name</dt><dd class="text-gray-800 dark:text-gray-200"><a href="{{ route('clients.show', $enquiry->client) }}" class="text-indigo-600 hover:underline">{{ $enquiry->client->name }}</a></dd></div>
                    <div><dt class="text-gray-400">Phone</dt><dd class="text-gray-800 dark:text-gray-200">{{ $enquiry->client->phone ?: '—' }}</dd></div>
                    <div><dt class="text-gray-400">Email</dt><dd class="text-gray-800 dark:text-gray-200">{{ $enquiry->client->email ?: '—' }}</dd></div>
                </dl>

                <div class="mt-4 border-t border-gray-100 pt-4 dark:border-gray-800">
                    @if ($enquiry->client->clientLogin)
                        <p class="text-sm text-emerald-600 dark:text-emerald-400">Portal access active — {{ $enquiry->client->clientLogin->user->email }}</p>
                    @else
                        @can('enquiries.create')
                            <form method="POST" action="{{ route('clients.portal-access', $enquiry->client) }}" onsubmit="return confirm('Create portal login for {{ $enquiry->client->email }}?')">
                                @csrf
                                <x-primary-button type="submit">Generate Portal Access</x-primary-button>
                            </form>
                            @unless ($enquiry->client->email)
                                <p class="mt-2 text-xs text-gray-400">Add an email address to the client first.</p>
                            @endunless
                        @endcan
                    @endif
                </div>
            </x-card>

            <x-card>
                <h3 class="mb-3 text-sm font-semibold text-gray-500">Site Visits</h3>
                @forelse ($enquiry->siteVisits as $visit)
                    <div class="border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <div class="flex items-center justify-between">
                            <span class="text-gray-700 dark:text-gray-300">{{ $visit->scheduled_at->format('d M, h:i A') }}</span>
                            <x-badge :status="$visit->status" />
                        </div>
                        <p class="text-xs text-gray-400">{{ $visit->assignedTo?->name }}</p>
                    </div>
                @empty
                    <p class="text-sm text-gray-400">None scheduled.</p>
                @endforelse
            </x-card>

            <x-card>
                <h3 class="mb-3 text-sm font-semibold text-gray-500">Quotations</h3>
                @forelse ($enquiry->quotations as $quotation)
                    <a href="{{ route('quotations.show', $quotation) }}" class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <span class="text-gray-700 dark:text-gray-300">{{ $quotation->quotation_no }}</span>
                        <x-badge :status="$quotation->status" />
                    </a>
                @empty
                    <p class="text-sm text-gray-400">None yet.</p>
                @endforelse
            </x-card>

            <x-card>
                <h3 class="mb-3 text-sm font-semibold text-gray-500">Work Orders</h3>
                @forelse ($enquiry->workOrders as $workOrder)
                    <a href="{{ route('work-orders.show', $workOrder) }}" class="flex items-center justify-between border-b border-gray-100 py-2 text-sm last:border-0 dark:border-gray-800">
                        <span class="text-gray-700 dark:text-gray-300">{{ $workOrder->work_order_no }}</span>
                        <x-badge :status="$workOrder->status" />
                    </a>
                @empty
                    <p class="text-sm text-gray-400">None yet.</p>
                @endforelse
            </x-card>
        </div>
    </div>
</x-app-layout>
