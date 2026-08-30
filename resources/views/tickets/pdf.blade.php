<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        h2 { font-size: 14px; margin-top: 20px; margin-bottom: 4px; color: #374151; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 10px; color: #6b7280; width: 30%; }
        .status { display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 11px; font-weight: bold; }
        .status-open { background: #fee2e2; color: #991b1b; }
        .status-in_progress { background: #fef3c7; color: #92400e; }
        .status-resolved { background: #d1fae5; color: #065f46; }
        .status-closed { background: #f3f4f6; color: #374151; }
        .comment { border-bottom: 1px solid #e5e7eb; padding: 8px 0; }
        .comment:last-child { border-bottom: none; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Ticket {{ $ticket->ticket_no }} &middot; {{ $ticket->workOrder?->work_order_no ?? '—' }}</p>

    <table>
        <tr><th>Title</th><td>{{ $ticket->title }}</td></tr>
        <tr><th>Work Order</th><td>{{ $ticket->workOrder ? $ticket->workOrder->work_order_no.' — '.$ticket->workOrder->title : 'Deleted work order' }}</td></tr>
        <tr><th>Client</th><td>{{ $ticket->workOrder?->client?->name ?? '—' }}</td></tr>
        <tr><th>Type</th><td>{{ Str::title($ticket->type) }}</td></tr>
        <tr><th>Priority</th><td>{{ Str::title($ticket->priority) }}</td></tr>
        <tr><th>Status</th><td><span class="status status-{{ $ticket->status }}">{{ Str::title(str_replace('_', ' ', $ticket->status)) }}</span></td></tr>
        <tr><th>Department</th><td>{{ $ticket->department?->name ?? '—' }}</td></tr>
        <tr><th>Assigned To</th><td>{{ $ticket->assignedTo?->name ?? '—' }}</td></tr>
        <tr><th>Raised By</th><td>{{ $ticket->raisedByName() }}</td></tr>
        <tr><th>Raised On</th><td>{{ $ticket->raisedAtIst() }}</td></tr>
        <tr><th>Due Date</th><td>{{ optional($ticket->due_date)->format('d M Y') ?? '—' }}</td></tr>
        @if ($ticket->resolved_at)
            <tr><th>Resolved On</th><td>{{ $ticket->resolved_at->timezone('Asia/Kolkata')->format('d-M-Y, h:i A') }} IST</td></tr>
        @endif
        @if ($ticket->closed_at)
            <tr><th>Closed On</th><td>{{ $ticket->closed_at->timezone('Asia/Kolkata')->format('d-M-Y, h:i A') }} IST</td></tr>
        @endif
        <tr><th>Description</th><td>{{ $ticket->description ?: '—' }}</td></tr>
        @foreach ($ticket->media as $attachment)
            <tr><th>Attachment</th><td>@include('work-orders.pdf._media', ['media' => $attachment, 'label' => $attachment->file_name])</td></tr>
        @endforeach
    </table>

    <h2>Comments</h2>
    @forelse ($ticket->comments as $comment)
        <div class="comment">
            <p>{{ $comment->comment }}</p>
            <p class="muted">{{ $comment->user?->name ?? 'Client' }} &middot; {{ $comment->created_at->timezone('Asia/Kolkata')->format('d-M-Y, h:i A') }} IST</p>
        </div>
    @empty
        <p class="muted">No comments yet.</p>
    @endforelse
</body>
</html>
