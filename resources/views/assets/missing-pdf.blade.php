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
    <p class="muted">Missing Equipment Report — as of {{ now()->format('d M Y') }}</p>

    @if ($assets->isEmpty())
        <p class="muted">No equipment is currently marked missing.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Asset ID</th><th>Name</th><th>Category</th><th>Last Known Site / WO</th>
                    <th>Reported By</th><th>Reported Date</th><th>Days Missing</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($assets as $asset)
                    <tr>
                        <td>{{ $asset->asset_code }}</td>
                        <td>{{ $asset->name }}</td>
                        <td>{{ $asset->category ?: '—' }}</td>
                        <td>{{ $asset->currentWorkOrder?->work_order_no ?? ucwords(str_replace('_', ' ', $asset->current_location)) }}</td>
                        <td>{{ $asset->missingSince?->updatedBy?->name ?? '—' }}</td>
                        <td>{{ $asset->missingSince?->created_at?->format('d M Y') ?? '—' }}</td>
                        <td>{{ $asset->missingSince ? $asset->missingSince->created_at->diffInDays(now()) : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
