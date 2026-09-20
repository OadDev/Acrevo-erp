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
                <p class="muted">Stock by Location — {{ $asset->name }}</p>
            </td>
            <td style="border: none; width: 40%;" class="text-right">
                <span class="badge">{{ $asset->asset_code }}</span>
            </td>
        </tr>
    </table>

    <p class="muted">Total Quantity: {{ $asset->quantity }}</p>

    @if ($asset->stocks->isEmpty())
        <p class="muted">No stock recorded yet.</p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Location</th>
                    <th class="text-right">Available</th>
                    <th class="text-right">In Transit</th>
                    <th class="text-right">Missing</th>
                    <th class="text-right">Damaged</th>
                    <th class="text-right">Under Repair</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($asset->stockByLocation() as $group)
                    @php $first = $group->first(); @endphp
                    <tr>
                        <td>
                            @if ($first->location === 'work_order' && $first->workOrder)
                                {{ $first->workOrder->work_order_no }}
                            @else
                                {{ ucwords(str_replace('_', ' ', $first->location)) }}
                            @endif
                        </td>
                        @foreach (\App\Models\AssetStock::STATUSES as $status)
                            <td class="text-right">{{ $group->firstWhere('status', $status)->quantity ?? 0 }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
