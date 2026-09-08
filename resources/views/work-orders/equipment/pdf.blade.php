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
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: bold; background: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body>
    <table style="border: none; margin-bottom: 8px;">
        <tr style="border: none;">
            <td style="border: none; width: 60%;">
                <h1>{{ config('app.name') }}</h1>
                <p class="muted">Equipment at Site — as of {{ now()->format('d M Y') }}</p>
            </td>
            <td style="border: none; width: 40%; text-align: right;">
                <span class="badge">{{ $workOrder->work_order_no }}</span>
            </td>
        </tr>
    </table>

    @if ($assets->isEmpty())
        <p class="muted">No equipment is currently assigned to this site.</p>
    @else
        <table>
            <thead>
                <tr><th>Asset ID</th><th>Name</th><th>Category</th><th>Serial Number</th><th>Qty Here</th><th>Status</th><th>Condition</th></tr>
            </thead>
            <tbody>
                @foreach ($assets as $asset)
                    <tr>
                        <td>{{ $asset->asset_code }}</td>
                        <td>{{ $asset->name }}</td>
                        <td>{{ $asset->category ?: '—' }}</td>
                        <td>{{ $asset->serial_number ?: '—' }}</td>
                        <td>{{ $asset->stocks->sum('quantity') }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $asset->status)) }}</td>
                        <td>{{ $asset->condition ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
