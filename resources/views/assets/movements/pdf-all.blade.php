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
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Movement History — as of {{ now()->format('d M Y') }}</p>

    @if ($movements->isEmpty())
        <p class="muted">No movements match the applied filters.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>S.No</th><th>Date</th><th>Asset</th><th>From</th><th>To</th><th>Type</th>
                    <th class="text-right">Qty</th><th>Status</th><th>Created By</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($movements as $movement)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $movement->moved_at->format('d M Y') }}</td>
                        <td>{{ $movement->asset->asset_code ?? '—' }} — {{ $movement->asset->name ?? 'Removed Asset' }}</td>
                        <td>{{ $movement->locationLabel($movement->from_location, $movement->fromWorkOrder) }}</td>
                        <td>{{ $movement->locationLabel($movement->to_location, $movement->toWorkOrder) }}</td>
                        <td>{{ $movement->typeLabel() }}</td>
                        <td class="text-right">{{ $movement->quantity }}</td>
                        <td>{{ ucwords($movement->status) }}</td>
                        <td>{{ $movement->createdBy?->name ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
