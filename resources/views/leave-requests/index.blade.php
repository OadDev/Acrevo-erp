<x-app-layout>
    <x-slot name="header">
        <x-page-header title="Leave Requests" subtitle="Submit and track leave requests." />
    </x-slot>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($canReview)
                <x-card :padded="false">
                    <div class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <h3 class="text-sm font-semibold text-gray-500">All Leave Requests</h3>
                        <form method="GET" action="{{ route('leave-requests.index') }}">
                            <x-select-input name="status" class="text-sm" onchange="this.form.submit()">
                                <option value="">All Statuses</option>
                                @foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $value => $label)
                                    <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                                @endforeach
                            </x-select-input>
                        </form>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-2">Employee</th>
                                    <th class="px-4 py-2">Type</th>
                                    <th class="px-4 py-2">Dates</th>
                                    <th class="px-4 py-2">Reason</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($allRequests as $leaveRequest)
                                    <tr>
                                        <td class="px-4 py-2 font-medium text-gray-800 dark:text-gray-200">{{ $leaveRequest->employee->name }}</td>
                                        <td class="px-4 py-2"><x-badge color="indigo" :status="$leaveRequest->type" /></td>
                                        <td class="px-4 py-2 text-gray-500">{{ $leaveRequest->from_date->format('d M Y') }} – {{ $leaveRequest->to_date->format('d M Y') }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $leaveRequest->reason ?: '—' }}</td>
                                        <td class="px-4 py-2">
                                            <x-badge :status="$leaveRequest->status" />
                                            @if ($leaveRequest->status !== 'pending')
                                                <p class="mt-1 text-xs text-gray-400">by {{ $leaveRequest->reviewedBy?->name ?? '—' }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2">
                                            @if ($leaveRequest->status === 'pending')
                                                <form method="POST" action="{{ route('leave-requests.review', $leaveRequest) }}" class="flex flex-wrap items-center gap-1">
                                                    @csrf
                                                    <x-text-input name="review_remarks" placeholder="Remarks (optional)" class="w-32 text-xs" />
                                                    <button name="status" value="approved" class="rounded-lg bg-emerald-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-emerald-500">Approve</button>
                                                    <button name="status" value="rejected" class="rounded-lg border border-rose-200 px-2.5 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-rose-500/30 dark:hover:bg-rose-500/10">Reject</button>
                                                </form>
                                            @elseif ($leaveRequest->review_remarks)
                                                <span class="text-xs text-gray-400">{{ $leaveRequest->review_remarks }}</span>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="6" class="px-4 py-6 text-center text-gray-400">No leave requests yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
                <div>{{ $allRequests->links() }}</div>
            @endif

            @unless ($canReview)
                <x-card :padded="false">
                    <h3 class="p-4 pb-0 text-sm font-semibold text-gray-500">My Leave Requests</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-100 text-sm dark:divide-gray-800">
                            <thead class="bg-gray-50 dark:bg-gray-800/50">
                                <tr class="text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                                    <th class="px-4 py-2">Type</th>
                                    <th class="px-4 py-2">Dates</th>
                                    <th class="px-4 py-2">Reason</th>
                                    <th class="px-4 py-2">Status</th>
                                    <th class="px-4 py-2"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                                @forelse ($myRequests as $leaveRequest)
                                    <tr>
                                        <td class="px-4 py-2"><x-badge color="indigo" :status="$leaveRequest->type" /></td>
                                        <td class="px-4 py-2 text-gray-500">{{ $leaveRequest->from_date->format('d M Y') }} – {{ $leaveRequest->to_date->format('d M Y') }}</td>
                                        <td class="px-4 py-2 text-gray-500">{{ $leaveRequest->reason ?: '—' }}</td>
                                        <td class="px-4 py-2">
                                            <x-badge :status="$leaveRequest->status" />
                                            @if ($leaveRequest->status !== 'pending' && $leaveRequest->review_remarks)
                                                <p class="mt-1 text-xs text-gray-400">{{ $leaveRequest->review_remarks }}</p>
                                            @endif
                                        </td>
                                        <td class="px-4 py-2">
                                            @if ($leaveRequest->status === 'pending')
                                                <form method="POST" action="{{ route('leave-requests.destroy', $leaveRequest) }}" onsubmit="return confirm('Withdraw this leave request?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button class="text-xs font-medium text-rose-600 hover:underline">Withdraw</button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @empty
                                    <tr><td colspan="5" class="px-4 py-6 text-center text-gray-400">You haven't submitted any leave requests yet.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endunless
        </div>

        <x-card>
            <h3 class="mb-4 text-sm font-semibold text-gray-500">Request Leave</h3>
            @if ($employee)
                <form method="POST" action="{{ route('leave-requests.store') }}" class="space-y-3">
                    @csrf
                    <x-select-input name="type" class="w-full">
                        @foreach (['casual' => 'Casual', 'sick' => 'Sick', 'earned' => 'Earned', 'unpaid' => 'Unpaid', 'other' => 'Other'] as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-select-input>
                    <div>
                        <x-input-label for="from_date" value="From" />
                        <x-text-input id="from_date" type="date" name="from_date" class="mt-1 w-full" required />
                    </div>
                    <div>
                        <x-input-label for="to_date" value="To" />
                        <x-text-input id="to_date" type="date" name="to_date" class="mt-1 w-full" required />
                    </div>
                    <x-textarea-input name="reason" rows="3" class="w-full" placeholder="Reason (optional)"></x-textarea-input>
                    <x-primary-button class="w-full justify-center">Submit Request</x-primary-button>
                </form>
            @else
                <p class="text-sm text-gray-400">No worker/employee profile is linked to your account, so leave can't be requested from here. Ask an Admin to link one.</p>
            @endif
        </x-card>
    </div>
</x-app-layout>
