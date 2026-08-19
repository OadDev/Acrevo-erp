<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 10px; color: #6b7280; width: 30%; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .status-approved { background: #d1fae5; color: #065f46; }
        .status-rejected { background: #fee2e2; color: #991b1b; }
        .status-pending { background: #f3f4f6; color: #374151; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Approval Request {{ $approvalRequest->approval_no }} &middot; {{ $approvalRequest->workOrder->work_order_no }}</p>

    <table>
        <tr><th>Title</th><td>{{ $approvalRequest->title }}</td></tr>
        <tr><th>Work Order</th><td>{{ $approvalRequest->workOrder->work_order_no }} &mdash; {{ $approvalRequest->workOrder->title }}</td></tr>
        <tr><th>Raised By</th><td>{{ $approvalRequest->raisedByName() }}</td></tr>
        <tr><th>Sent To</th><td>{{ $approvalRequest->sentToName() }}</td></tr>
        <tr><th>Request Date</th><td>{{ $approvalRequest->created_at->format('d M Y, h:i A') }}</td></tr>
        <tr><th>Status</th><td><span class="status status-{{ $approvalRequest->status }}">{{ Str::title($approvalRequest->status) }}</span></td></tr>
        @if ($approvalRequest->description)
            <tr><th>Details</th><td>{{ $approvalRequest->description }}</td></tr>
        @endif
        @if ($approvalRequest->getFirstMedia('attachment'))
            <tr><th>Attachment</th><td>@include('work-orders.pdf._media', ['media' => $approvalRequest->getFirstMedia('attachment'), 'label' => $approvalRequest->getFirstMedia('attachment')->file_name])</td></tr>
        @endif
        @if ($approvalRequest->status !== 'pending')
            <tr><th>Responded By</th><td>{{ $approvalRequest->respondedBy?->name ?? '—' }}</td></tr>
            <tr><th>Response Date</th><td>{{ $approvalRequest->responded_at?->format('d M Y, h:i A') ?? '—' }}</td></tr>
            @if ($approvalRequest->response_note)
                <tr><th>Response Note</th><td>{{ $approvalRequest->response_note }}</td></tr>
            @endif
        @endif
    </table>
</body>
</html>
