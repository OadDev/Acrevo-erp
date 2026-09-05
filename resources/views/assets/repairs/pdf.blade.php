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
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: bold; background: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body>
    <table style="border: none; margin-bottom: 8px;">
        <tr style="border: none;">
            <td style="border: none; width: 60%;">
                <h1>{{ config('app.name') }}</h1>
                <p class="muted">Repair History — {{ $asset->name }}</p>
            </td>
            <td style="border: none; width: 40%; text-align: right;">
                <span class="badge">{{ $asset->asset_code }}</span>
            </td>
        </tr>
    </table>

    @if ($asset->repairs->isEmpty())
        <p class="muted">No repairs recorded yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Reported</th><th>Completed</th><th>Type</th><th>Issue</th><th>Technician / Vendor</th>
                    <th>Warranty</th><th class="text-right">Cost</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($asset->repairs as $repair)
                    <tr>
                        <td>{{ $repair->reported_date->format('d M Y') }}</td>
                        <td>{{ optional($repair->completed_date)->format('d M Y') ?? '—' }}</td>
                        <td>{{ ucwords($repair->repair_type) }}</td>
                        <td>{{ $repair->issue_description }}</td>
                        <td>{{ $repair->technician_vendor ?: '—' }}</td>
                        <td>{{ $repair->is_warranty_repair ? 'Warranty' : 'Paid' }}</td>
                        <td class="text-right">{{ $repair->cost !== null ? 'Rs. '.number_format($repair->cost, 2) : '—' }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $repair->status)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
