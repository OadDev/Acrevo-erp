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
    <p class="muted">Repair History — as of {{ now()->format('d M Y') }}</p>

    @if ($repairs->isEmpty())
        <p class="muted">No repairs match the applied filters.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>S.No</th><th>Reported</th><th>Asset</th><th>Type</th><th class="text-right">Qty</th>
                    <th>Technician / Vendor</th><th>Warranty</th><th class="text-right">Cost</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($repairs as $repair)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $repair->reported_date->format('d M Y') }}</td>
                        <td>{{ $repair->asset->asset_code ?? '—' }} — {{ $repair->asset->name ?? 'Removed Asset' }}</td>
                        <td>{{ ucwords($repair->repair_type) }}</td>
                        <td class="text-right">{{ $repair->quantity }}</td>
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
