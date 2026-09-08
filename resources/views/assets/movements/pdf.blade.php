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
                <p class="muted">Movement History — {{ $asset->name }}</p>
            </td>
            <td style="border: none; width: 40%; text-align: right;">
                <span class="badge">{{ $asset->asset_code }}</span>
            </td>
        </tr>
    </table>

    @if ($asset->movements->isEmpty())
        <p class="muted">No movements recorded yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Date</th><th>From</th><th>To</th><th>Type</th><th>Status</th><th>Updated By</th><th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($asset->movements as $movement)
                    <tr>
                        <td>{{ $movement->moved_at->format('d M Y') }}</td>
                        <td>{{ $movement->locationLabel($movement->from_location, $movement->fromWorkOrder) }}</td>
                        <td>{{ $movement->locationLabel($movement->to_location, $movement->toWorkOrder) }}</td>
                        <td>{{ $movement->typeLabel() }}</td>
                        <td>{{ ucwords($movement->status) }}</td>
                        <td>{{ ($movement->status === 'confirmed' ? $movement->confirmedBy : $movement->createdBy)?->name }}</td>
                        <td>{{ $movement->remarks ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
