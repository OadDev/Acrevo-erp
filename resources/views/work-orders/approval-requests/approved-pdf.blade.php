<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 9px; color: #6b7280; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Approved Requests &middot; {{ $workOrder->work_order_no }} &mdash; {{ $workOrder->title }}</p>
    <p class="muted">Generated {{ now()->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</p>

    <table>
        <thead>
            <tr>
                <th>Approval No</th>
                <th>Title</th>
                <th>Raised By</th>
                <th>Sent To</th>
                <th>Request Date</th>
                <th>Approved By</th>
                <th>Approved Date</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($approvalRequests as $approval)
                <tr>
                    <td>{{ $approval->approval_no }}</td>
                    <td>{{ $approval->title }}</td>
                    <td>{{ $approval->raisedByName() }}</td>
                    <td>{{ $approval->sentToName() }}</td>
                    <td>{{ $approval->requestedAtIst() }}</td>
                    <td>{{ $approval->respondedBy?->name ?? '—' }}</td>
                    <td>{{ $approval->respondedAtIst() ?? '—' }}</td>
                </tr>
                @if ($approval->description)
                    <tr><td></td><td colspan="6" class="muted">{{ $approval->description }}</td></tr>
                @endif
                @foreach ($approval->getMedia('attachment') as $attachment)
                    <tr><td></td><td colspan="6">@include('work-orders.pdf._media', ['media' => $attachment, 'label' => $attachment->file_name])</td></tr>
                @endforeach
            @empty
                <tr><td colspan="7">No approved requests yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
