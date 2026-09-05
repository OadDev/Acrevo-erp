<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 10px; color: #6b7280; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Equipment Requests Report — as of {{ now()->format('d M Y') }}</p>

    @if ($equipmentRequests->isEmpty())
        <p class="muted">No equipment requests match the applied filters.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Requested</th><th>Item</th><th>Qty</th><th>Work Order</th>
                    <th>Requested By</th><th>Status</th><th>Approved By</th><th>Asset</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($equipmentRequests as $equipmentRequest)
                    <tr>
                        <td>{{ $equipmentRequest->created_at->format('d M Y') }}</td>
                        <td>{{ $equipmentRequest->item_name }}</td>
                        <td>{{ $equipmentRequest->quantity }}</td>
                        <td>{{ $equipmentRequest->workOrder?->work_order_no ?? 'Company Store' }}</td>
                        <td>{{ $equipmentRequest->requestedBy?->name }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $equipmentRequest->status)) }}</td>
                        <td>{{ $equipmentRequest->approvedBy?->name ?? '—' }}</td>
                        <td>{{ $equipmentRequest->asset?->asset_code ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
