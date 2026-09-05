<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        h2 { font-size: 13px; margin: 18px 0 6px; color: #374151; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 10px; color: #6b7280; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: bold; background: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body>
    <table style="border: none; margin-bottom: 8px;">
        <tr style="border: none;">
            <td style="border: none; width: 60%;">
                <h1>{{ config('app.name') }}</h1>
                <p class="muted">Equipment History Report</p>
            </td>
            <td style="border: none; width: 40%; text-align: right;">
                <span class="badge">{{ $workOrder->work_order_no }}</span>
            </td>
        </tr>
    </table>

    <h2>Movement History (Assigned, Transferred, Returned)</h2>
    @if ($movements->isEmpty())
        <p class="muted">No movements recorded for this site.</p>
    @else
        <table>
            <thead>
                <tr><th>Date</th><th>Asset</th><th>From</th><th>To</th><th>Type</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach ($movements as $movement)
                    <tr>
                        <td>{{ $movement->moved_at->format('d M Y') }}</td>
                        <td>{{ $movement->asset->asset_code }} — {{ $movement->asset->name }}</td>
                        <td>{{ $movement->locationLabel($movement->from_location, $movement->fromWorkOrder) }}</td>
                        <td>{{ $movement->locationLabel($movement->to_location, $movement->toWorkOrder) }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $movement->type)) }}</td>
                        <td>{{ ucwords($movement->status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Repairs Logged at This Site</h2>
    @if ($repairs->isEmpty())
        <p class="muted">No repairs recorded for this site.</p>
    @else
        <table>
            <thead>
                <tr><th>Reported</th><th>Asset</th><th>Type</th><th>Issue</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach ($repairs as $repair)
                    <tr>
                        <td>{{ $repair->reported_date->format('d M Y') }}</td>
                        <td>{{ $repair->asset->asset_code }} — {{ $repair->asset->name }}</td>
                        <td>{{ ucwords($repair->repair_type) }}</td>
                        <td>{{ $repair->issue_description }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $repair->status)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Missing Equipment Incidents at This Site</h2>
    @if ($missingLogs->isEmpty())
        <p class="muted">No missing-equipment incidents recorded for this site.</p>
    @else
        <table>
            <thead>
                <tr><th>Date</th><th>Asset</th><th>Reported By</th><th>Reason / Remarks</th></tr>
            </thead>
            <tbody>
                @foreach ($missingLogs as $log)
                    <tr>
                        <td>{{ $log->created_at->format('d M Y, h:i A') }}</td>
                        <td>{{ $log->asset->asset_code }} — {{ $log->asset->name }}</td>
                        <td>{{ $log->updatedBy?->name }}</td>
                        <td>{{ $log->reason ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
