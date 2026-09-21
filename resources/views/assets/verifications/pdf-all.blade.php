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
    <p class="muted">Verification History — as of {{ now()->format('d M Y') }}</p>

    @if ($verifications->isEmpty())
        <p class="muted">No verifications match the applied filters.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>S.No</th><th>Verified On</th><th>Asset</th><th>Work Order</th><th class="text-right">Qty</th>
                    <th>Result</th><th>Condition</th><th>Verified By</th><th>Remarks</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($verifications as $verification)
                    <tr>
                        <td>{{ $loop->iteration }}</td>
                        <td>{{ $verification->verified_at->format('d M Y') }}</td>
                        <td>{{ $verification->asset->asset_code ?? '—' }} — {{ $verification->asset->name ?? 'Removed Asset' }}</td>
                        <td>{{ $verification->workOrder?->work_order_no ?? '—' }}</td>
                        <td class="text-right">{{ $verification->quantity }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $verification->result)) }}</td>
                        <td>{{ $verification->condition ?: '—' }}</td>
                        <td>{{ $verification->verifiedBy?->name ?? '—' }}</td>
                        <td>{{ $verification->remarks ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
