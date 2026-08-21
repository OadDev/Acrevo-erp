@php
    $sectionLabels = \App\Support\WorkOrderPdfSections::SECTIONS;
@endphp

@if (in_array('site', $sections, true))
    <h2 class="section-title">{{ $sectionLabels['site'] }}</h2>
    @if ($workOrder->site)
        <table class="meta-table">
            <tr><td class="muted">Site ID</td><td>{{ $workOrder->site->site_no }}</td><td class="muted">Client</td><td>{{ $workOrder->client?->name ?? '—' }}</td></tr>
            <tr><td class="muted">Address</td><td colspan="3">{{ collect([$workOrder->site->address, $workOrder->site->city, $workOrder->site->state, $workOrder->site->pincode])->filter()->join(', ') ?: '—' }}</td></tr>
            @if ($workOrder->site->construction_site_location)
                <tr><td class="muted">Construction Site Location</td><td colspan="3">{{ $workOrder->site->construction_site_location }}</td></tr>
            @endif
            @if ($workOrder->site->client_living_location)
                <tr><td class="muted">Client Living Location</td><td colspan="3">{{ $workOrder->site->client_living_location }}</td></tr>
            @endif
            <tr><td class="muted">Site Contact</td><td colspan="3">{{ $workOrder->site->site_contact_name ?? '—' }} {{ $workOrder->site->site_contact_phone ? '· '.$workOrder->site->site_contact_phone : '' }}</td></tr>
        </table>
    @else
        <p class="empty">No site linked to this work order.</p>
    @endif
@endif

@if (in_array('overview', $sections, true))
    <h2 class="section-title">{{ $sectionLabels['overview'] }}</h2>
    <table class="meta-table">
        <tr><td class="muted">Client</td><td>{{ $workOrder->client?->name ?? '—' }}</td><td class="muted">Priority</td><td>{{ Str::title($workOrder->priority) }}</td></tr>
        <tr><td class="muted">Deadline</td><td>{{ optional($workOrder->deadline)->format('d M Y') ?? '—' }}</td><td class="muted">Budget</td><td>Rs. {{ number_format($workOrder->budget_amount ?? 0, 2) }}</td></tr>
        <tr><td class="muted">Execution Method</td><td colspan="3">{{ \App\Models\WorkOrder::EXECUTION_WAYS[$workOrder->execution_way] ?? '—' }}</td></tr>
    </table>
    <table class="meta-table" style="margin-top:4px;">
        <tr>
            <td class="muted">Material</td><td>Rs. {{ number_format($workOrder->estimated_material_budget ?? 0, 2) }}</td>
            <td class="muted">Man Power</td><td>Rs. {{ number_format($workOrder->estimated_labour_budget ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td class="muted">Equipment / Machinery</td><td>Rs. {{ number_format($workOrder->estimated_equipment_budget ?? 0, 2) }}</td>
            <td class="muted">Transport</td><td>Rs. {{ number_format($workOrder->estimated_transport_budget ?? 0, 2) }}</td>
        </tr>
        <tr>
            <td class="muted">Miscellaneous / Contingency</td><td>Rs. {{ number_format($workOrder->estimated_misc_budget ?? 0, 2) }}</td>
            <td></td><td></td>
        </tr>
    </table>
    <p style="margin-top:8px;"><strong>Scope of Work</strong><br>{{ $workOrder->scope ?: 'No scope defined.' }}</p>

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
        <p style="margin-top:14px;"><strong>Planned Budget Details</strong></p>
        @foreach ($budgetCategoryLabels as $category => $label)
            @if ($budgetItemsByCategory->has($category))
                <p style="margin-bottom:2px;">{{ $label }}</p>
                <table>
                    <thead>
                        <tr>
                            <th>{{ $category === 'labour' ? 'Designation' : 'Item' }}</th>
                            @if ($category === 'material')
                                <th>Brand</th>
                                <th>Size</th>
                            @endif
                            @if ($category !== 'labour')
                                <th>Unit</th>
                            @endif
                            <th class="text-right">{{ $category === 'labour' ? 'Count' : 'Qty' }}</th>
                            @if ($category === 'labour')
                                <th class="text-right">Hours</th>
                            @endif
                            <th class="text-right">{{ $category === 'labour' ? 'Wage Rate' : 'Rate' }}</th>
                            @if ($category !== 'labour')
                                <th>Vendor</th>
                            @endif
                            <th class="text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($budgetItemsByCategory[$category] as $item)
                            <tr>
                                <td>{{ $item->name }}</td>
                                @if ($category === 'material')
                                    <td>{{ $item->brand ?? '—' }}</td>
                                    <td>{{ $item->size ?? '—' }}</td>
                                @endif
                                @if ($category !== 'labour')
                                    <td>{{ $item->unit ?? '—' }}</td>
                                @endif
                                <td class="text-right">{{ $item->quantity }}</td>
                                @if ($category === 'labour')
                                    <td class="text-right">{{ $item->hours ?? '—' }}</td>
                                @endif
                                <td class="text-right">Rs. {{ number_format($item->rate ?? 0, 2) }}</td>
                                @if ($category !== 'labour')
                                    <td>{{ $item->vendor ?? '—' }}</td>
                                @endif
                                <td class="text-right">Rs. {{ number_format($item->amount ?? 0, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach

        @if ($workOrder->timeSchedules->isNotEmpty())
            <p style="margin-top:8px; margin-bottom:2px;">Time Schedule</p>
            <table>
                <thead><tr><th>Time to Finish</th><th>Unit</th><th>Remark</th></tr></thead>
                <tbody>
                    @foreach ($workOrder->timeSchedules as $schedule)
                        <tr><td>{{ $schedule->time_to_finish }}</td><td>{{ $schedule->unit ?? '—' }}</td><td>{{ $schedule->remark ?? '—' }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    @endif

    @if ($workOrder->statusLogs->isNotEmpty())
        <table>
            <thead><tr><th>Status</th><th>Changed By</th><th>Date</th><th>Remarks</th></tr></thead>
            <tbody>
                @foreach ($workOrder->statusLogs as $log)
                    <tr>
                        <td>{{ Str::title(str_replace('_', ' ', $log->to_status)) }}</td>
                        <td>{{ $log->changedBy?->name ?? 'System' }}</td>
                        <td>{{ $log->changed_at->format('d M Y, h:i A') }}</td>
                        <td>{{ $log->remarks ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

@if (in_array('team', $sections, true))
    <h2 class="section-title">{{ $sectionLabels['team'] }}</h2>
    @if ($workOrder->executiveTeams->isNotEmpty())
        <table>
            <thead><tr><th>Team</th><th>Leader</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($workOrder->executiveTeams as $assignment)
                    <tr>
                        <td>{{ $assignment->executiveTeam?->name ?? 'Unknown team' }} ({{ $assignment->executiveTeam?->team_number }})</td>
                        <td>{{ $assignment->executiveTeam?->teamLeader?->name ?? '—' }}</td>
                        <td>{{ $assignment->unassigned_at ? 'Unassigned' : 'Active' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No executive team assigned.</p>
    @endif
    @if ($workOrder->subContractors->isNotEmpty())
        <table>
            <thead><tr><th>Sub-Contractor</th><th>Email</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($workOrder->subContractors as $assignment)
                    <tr>
                        <td>{{ $assignment->user?->name ?? 'Unknown user' }}</td>
                        <td>{{ $assignment->user?->email ?? '—' }}</td>
                        <td>{{ $assignment->unassigned_at ? 'Unassigned' : 'Active' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

@if (in_array('checklist', $sections, true))
    <h2 class="section-title">{{ $sectionLabels['checklist'] }}</h2>
    @forelse ($workOrder->dailyChecklists as $checklist)
        <p style="margin-bottom:2px;"><strong>{{ $checklist->title ?? 'Daily Work' }}</strong> — {{ $checklist->date->format('d M Y') }} ({{ $checklist->executiveTeam?->name ?? '—' }})</p>
        <table>
            <thead><tr><th>Item</th><th>Status</th><th>Done By</th><th>Done At</th></tr></thead>
            <tbody>
                @foreach ($checklist->checklistItems as $item)
                    <tr>
                        <td>{{ $item->description }}</td>
                        <td>{{ $item->is_done ? 'Done' : 'Pending' }}</td>
                        <td>{{ $item->doneBy?->name ?? '—' }}</td>
                        <td>{{ $item->done_at?->format('d M Y') ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @php $checklistProof = $checklist->checklistItems->flatMap(fn ($i) => $i->media); @endphp
        @foreach ($checklistProof as $media)
            @include('work-orders.pdf._media', ['media' => $media, 'label' => 'Proof'])
        @endforeach
    @empty
        <p class="empty">No daily work entries yet.</p>
    @endforelse
@endif

@if (in_array('progress', $sections, true))
    <h2 class="section-title">{{ $sectionLabels['progress'] }}</h2>
    @if ($workOrder->media->isNotEmpty())
        <p><strong>Files Stored on This Work Order</strong> (videos are not included in this PDF)</p>
        @foreach ($workOrder->media as $item)
            @include('work-orders.pdf._media', ['media' => $item, 'label' => Str::title(str_replace('_', ' ', $item->collection_name)).' ('.$item->created_at->format('d M Y').')'])
        @endforeach
    @endif
    @forelse ($workOrder->dailyProgressReports as $report)
        <p style="margin-top:8px; margin-bottom:2px;"><strong>{{ $report->date->format('d M Y') }}</strong> — {{ $report->executiveTeam?->name ?? '—' }}</p>
        <p>Completed: {{ $report->completed_work }}</p>
        @if ($report->pending_work)<p>Pending: {{ $report->pending_work }}</p>@endif
        @if ($report->problems)<p>Problems: {{ $report->problems }}</p>@endif
    @empty
        <p class="empty">No progress reports yet.</p>
    @endforelse
@endif

@if (in_array('materials', $sections, true))
    <h2 class="section-title">{{ $sectionLabels['materials'] }}</h2>
    <table class="meta-table">
        <tr><td class="muted">Allocated Material Budget</td><td>Rs. {{ number_format($workOrder->estimated_material_budget ?? 0, 2) }}</td><td class="muted">Material Inward Total</td><td>Rs. {{ number_format($workOrder->materialEntries->sum('amount'), 2) }}</td></tr>
    </table>
    @if ($workOrder->materialEntries->isNotEmpty())
        <table>
            <thead><tr><th>Date</th><th>Material</th><th>Qty</th><th>Unit</th><th>Rate</th><th class="text-right">Amount</th><th>Supplier</th></tr></thead>
            <tbody>
                @foreach ($workOrder->materialEntries as $entry)
                    <tr>
                        <td>{{ $entry->entry_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $entry->material_name }}</td>
                        <td>{{ $entry->quantity }}</td>
                        <td>{{ $entry->unit }}</td>
                        <td>Rs. {{ number_format($entry->rate, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($entry->amount, 2) }}</td>
                        <td>{{ $entry->vendor ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No material inward entries yet.</p>
    @endif
    @if ($workOrder->materialUsageEntries->isNotEmpty())
        <p style="margin-top:8px;"><strong>Daily Material Used Entry</strong></p>
        <table>
            <thead><tr><th>Date</th><th>Material</th><th>Qty</th><th>Unit</th></tr></thead>
            <tbody>
                @foreach ($workOrder->materialUsageEntries as $entry)
                    <tr>
                        <td>{{ $entry->date->format('d M Y') }}</td>
                        <td>{{ $entry->material_name }}</td>
                        <td>{{ $entry->quantity }}</td>
                        <td>{{ $entry->unit }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

@if (in_array('manpower', $sections, true))
    <h2 class="section-title">{{ $sectionLabels['manpower'] }}</h2>
    <table class="meta-table">
        <tr><td class="muted">Allocated Man Power Budget</td><td>Rs. {{ number_format($workOrder->estimated_labour_budget ?? 0, 2) }}</td><td class="muted">Used Man Power Total</td><td>Rs. {{ number_format($workOrder->labourEntries->sum('amount'), 2) }}</td></tr>
    </table>
    @if ($workOrder->timeSchedules->isNotEmpty())
        <p style="margin-top:8px;"><strong>Allocated Time Schedule</strong></p>
        <table>
            <thead><tr><th>Schedule Time to Finish</th><th>Units</th><th>Remarks</th></tr></thead>
            <tbody>
                @foreach ($workOrder->timeSchedules as $schedule)
                    <tr><td>{{ $schedule->time_to_finish }}</td><td>{{ $schedule->unit ?? '—' }}</td><td>{{ $schedule->remark ?? '—' }}</td></tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @if ($workOrder->labourEntries->isNotEmpty())
        <table>
            <thead><tr><th>Date</th><th>Designation</th><th>Nos x Rate</th><th>Target Hrs</th><th>Actual Time</th><th class="text-right">Amount</th></tr></thead>
            <tbody>
                @foreach ($workOrder->labourEntries as $entry)
                    <tr>
                        <td>{{ $entry->entry_date?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $entry->labour_type }}</td>
                        <td>{{ $entry->count }} × Rs. {{ $entry->wage_rate }}</td>
                        <td>{{ $entry->hours ?? '—' }}</td>
                        <td>{{ $entry->total_time_to_finish ?? '—' }}</td>
                        <td class="text-right">Rs. {{ number_format($entry->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No labour entries yet.</p>
    @endif
@endif

@if (in_array('mb', $sections, true))
    @php
        $scheduleBooks = $workOrder->measurementBooks->where('type', 'schedule');
        $actualBooks = $workOrder->measurementBooks->where('type', 'actual');
    @endphp
    <h2 class="section-title">{{ $sectionLabels['mb'] }}</h2>
    @if ($scheduleBooks->isNotEmpty())
        <p><strong>Allocated Work Schedule</strong></p>
        @foreach ($scheduleBooks as $mb)
            <table>
                <thead><tr><th>Work Description</th><th>L</th><th>B</th><th>D</th><th>Total</th><th>Unit</th></tr></thead>
                <tbody>
                    @foreach ($mb->items as $item)
                        <tr><td>{{ $item->item_description }}</td><td>{{ $item->length ?? '—' }}</td><td>{{ $item->breadth ?? '—' }}</td><td>{{ $item->height ?? '—' }}</td><td>{{ $item->quantity }}</td><td>{{ $item->unit }}</td></tr>
                    @endforeach
                </tbody>
            </table>
        @endforeach
    @endif
    <p style="margin-top:8px;"><strong>Actual Work Done</strong></p>
    @forelse ($actualBooks as $mb)
        <p style="margin-bottom:2px;">{{ $mb->mb_no }} — {{ $mb->date->format('d M Y') }} — {{ $mb->description }}</p>
        <table>
            <thead><tr><th>Work Name</th><th>L</th><th>B</th><th>D/T/H</th><th>Total</th><th>Unit</th><th class="text-right">Rate</th><th class="text-right">Amount</th></tr></thead>
            <tbody>
                @foreach ($mb->items as $item)
                    <tr>
                        <td>{{ $item->item_description }}</td><td>{{ $item->length ?? '—' }}</td><td>{{ $item->breadth ?? '—' }}</td><td>{{ $item->height ?? '—' }}</td>
                        <td>{{ $item->quantity }}</td><td>{{ $item->unit }}</td><td class="text-right">Rs. {{ number_format($item->rate, 2) }}</td><td class="text-right">Rs. {{ number_format($item->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @empty
        <p class="empty">No work done recorded yet.</p>
    @endforelse
    @if ($workOrder->attendances->isNotEmpty())
        <p style="margin-top:8px;"><strong>Worker Attendance</strong></p>
        <table>
            <thead><tr><th>Worker</th><th>Date</th><th>In</th><th>Out</th><th>Hours</th><th class="text-right">Salary</th></tr></thead>
            <tbody>
                @foreach ($workOrder->attendances as $attendance)
                    <tr>
                        <td>{{ $attendance->employee?->name }}</td><td>{{ $attendance->date->format('d M Y') }}</td>
                        <td>{{ $attendance->check_in ?? '—' }}</td><td>{{ $attendance->check_out ?? '—' }}</td>
                        <td>{{ $attendance->hours_worked ?? '—' }}</td><td class="text-right">{{ $attendance->salary ? 'Rs. '.number_format($attendance->salary, 2) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
@endif

@if (in_array('summary', $sections, true))
    <h2 class="section-title">{{ $sectionLabels['summary'] }}</h2>
    @if ($workOrder->summaries->isNotEmpty())
        <table>
            <thead><tr><th>Date</th><th>Work Done</th><th>Responsibility</th><th>Detail/Reason</th><th>Days Client Bear</th><th>Remaining Days</th></tr></thead>
            <tbody>
                @foreach ($workOrder->summaries as $entry)
                    <tr>
                        <td>{{ $entry->entry_date->format('d M Y') }}</td>
                        <td>{{ $entry->status === 'done' ? 'Done' : 'No' }}</td>
                        <td>{{ Str::title($entry->responsibility) }}</td>
                        <td>{{ $entry->work_detail ?? '—' }}</td>
                        <td>{{ $entry->client_bear_days ?? '—' }}</td>
                        <td>{{ $entry->remaining_construction_days ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No summary entries yet.</p>
    @endif
@endif

@if (in_array('ledger', $sections, true))
    <h2 class="section-title">{{ $sectionLabels['ledger'] }}</h2>
    @if ($workOrder->ledgers->isNotEmpty())
        <table>
            <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Type</th><th class="text-right">Amount</th><th class="text-right">Balance</th></tr></thead>
            <tbody>
                @foreach ($workOrder->ledgers as $entry)
                    <tr>
                        <td>{{ $entry->entry_date->format('d M Y') }}</td>
                        <td>{{ $entry->category ?? '—' }}</td>
                        <td>{{ $entry->description ?? '—' }}</td>
                        <td>{{ Str::title($entry->type) }}</td>
                        <td class="text-right">Rs. {{ number_format($entry->amount, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($entry->balance, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @php $ledgerBills = $workOrder->ledgers->filter(fn ($e) => $e->getFirstMedia('bill')); @endphp
        @if ($ledgerBills->isNotEmpty())
            <p style="margin-top:8px;"><strong>Bills &amp; Supporting Documents</strong></p>
            @foreach ($ledgerBills as $entry)
                @include('work-orders.pdf._media', ['media' => $entry->getFirstMedia('bill'), 'label' => $entry->entry_date->format('d M Y').' — '.($entry->category ?? $entry->description ?? 'Bill')])
            @endforeach
        @endif
    @else
        <p class="empty">No ledger entries yet.</p>
    @endif
@endif

@if (in_array('company-ledger', $sections, true))
    <h2 class="section-title">{{ $sectionLabels['company-ledger'] }}</h2>
    @if ($workOrder->companyLedgers->isNotEmpty())
        <table>
            <thead><tr><th>Date</th><th>Category</th><th>Description</th><th>Type</th><th class="text-right">Amount</th><th class="text-right">Balance</th></tr></thead>
            <tbody>
                @foreach ($workOrder->companyLedgers as $entry)
                    <tr>
                        <td>{{ $entry->entry_date->format('d M Y') }}</td>
                        <td>{{ $entry->category ?? '—' }}</td>
                        <td>{{ $entry->description ?? '—' }}</td>
                        <td>{{ Str::title($entry->type) }}</td>
                        <td class="text-right">Rs. {{ number_format($entry->amount, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($entry->balance, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @php $companyLedgerBills = $workOrder->companyLedgers->filter(fn ($e) => $e->getFirstMedia('bill')); @endphp
        @if ($companyLedgerBills->isNotEmpty())
            <p style="margin-top:8px;"><strong>Bills &amp; Supporting Documents</strong></p>
            @foreach ($companyLedgerBills as $entry)
                @include('work-orders.pdf._media', ['media' => $entry->getFirstMedia('bill'), 'label' => $entry->entry_date->format('d M Y').' — '.($entry->category ?? $entry->description ?? 'Bill')])
            @endforeach
        @endif
    @else
        <p class="empty">No company ledger entries yet.</p>
    @endif
@endif

@if (in_array('qc', $sections, true))
    <h2 class="section-title">{{ $sectionLabels['qc'] }}</h2>
    @if ($workOrder->qcInspections->isNotEmpty())
        <table>
            <thead><tr><th>Type</th><th>Date</th><th>Status</th><th>Inspector</th><th>Remarks</th></tr></thead>
            <tbody>
                @foreach ($workOrder->qcInspections as $inspection)
                    <tr>
                        <td>{{ Str::title($inspection->inspection_type) }}</td>
                        <td>{{ $inspection->inspection_date->format('d M Y') }}</td>
                        <td>{{ Str::title($inspection->status) }}</td>
                        <td>{{ $inspection->inspectedBy?->name ?? '—' }}</td>
                        <td>{{ $inspection->remarks ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No QC inspections yet.</p>
    @endif
@endif

@if (in_array('approvals', $sections, true))
    <h2 class="section-title">{{ $sectionLabels['approvals'] }}</h2>
    @if ($workOrder->approvalRequests->isNotEmpty())
        <table>
            <thead><tr><th>Approval No</th><th>Title</th><th>Raised By</th><th>Sent To</th><th>Date</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($workOrder->approvalRequests as $approval)
                    <tr>
                        <td>{{ $approval->approval_no }}</td>
                        <td>{{ $approval->title }}</td>
                        <td>{{ $approval->raisedByName() }}</td>
                        <td>{{ $approval->sentToName() }}</td>
                        <td>{{ $approval->requestedAtIst() }}</td>
                        <td>{{ Str::title($approval->status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
        @php $approvalAttachments = $workOrder->approvalRequests->filter(fn ($a) => $a->getFirstMedia('attachment')); @endphp
        @if ($approvalAttachments->isNotEmpty())
            <p style="margin-top:8px;"><strong>Attachments</strong></p>
            @foreach ($approvalAttachments as $approval)
                @include('work-orders.pdf._media', ['media' => $approval->getFirstMedia('attachment'), 'label' => $approval->approval_no.' — '.$approval->title])
            @endforeach
        @endif
    @else
        <p class="empty">No approval requests yet.</p>
    @endif
@endif

@if (in_array('tickets', $sections, true))
    <h2 class="section-title">{{ $sectionLabels['tickets'] }}</h2>
    @if ($workOrder->tickets->isNotEmpty())
        <table>
            <thead><tr><th>Ticket No</th><th>Title</th><th>Type</th><th>Priority</th><th>Status</th></tr></thead>
            <tbody>
                @foreach ($workOrder->tickets as $ticket)
                    <tr>
                        <td>{{ $ticket->ticket_no }}</td>
                        <td>{{ $ticket->title }}</td>
                        <td>{{ Str::title($ticket->type) }}</td>
                        <td>{{ Str::title($ticket->priority) }}</td>
                        <td>{{ Str::title($ticket->status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="empty">No tickets raised.</p>
    @endif
@endif
