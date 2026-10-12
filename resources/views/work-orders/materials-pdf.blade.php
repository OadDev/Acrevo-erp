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
        .totals-row td { font-weight: bold; background: #f9fafb; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">
        Material Inward — {{ $workOrder->work_order_no }}
        @if ($from || $to)
            — {{ $from ? \Carbon\Carbon::parse($from)->format('d M Y') : 'Start' }} to {{ $to ? \Carbon\Carbon::parse($to)->format('d M Y') : 'Today' }}
        @endif
    </p>
    @if ($material || $vendor || $scope)
        <p class="muted">
            Filtered by:
            @if ($material) Material "{{ $material }}" @endif
            @if ($vendor) &middot; Supplier "{{ $vendor }}" @endif
            @if ($scope) &middot; Scope "{{ ucfirst($scope) }}" @endif
        </p>
    @endif

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Material Description</th>
                <th class="text-right">Nos</th>
                <th>Unit</th>
                <th class="text-right">Rate/Unit</th>
                <th class="text-right">Total Rate</th>
                <th>Scope</th>
                <th>Supplier Details</th>
                <th>Delivery Vehicle Details</th>
                <th>Remarks</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->entry_date?->format('d M Y') ?? '—' }}</td>
                    <td>{{ $entry->material_name }}</td>
                    <td class="text-right">{{ $entry->quantity }}</td>
                    <td>{{ $entry->unit }}</td>
                    <td class="text-right">Rs. {{ number_format($entry->rate, 2) }}</td>
                    <td class="text-right">Rs. {{ number_format($entry->amount, 2) }}</td>
                    <td>{{ $entry->scope ? ucfirst($entry->scope) : '—' }}</td>
                    <td>{{ $entry->vendor ?? '—' }}</td>
                    <td>{{ $entry->delivery_vehicle_details ?? '—' }}</td>
                    <td>{{ $entry->remarks ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="10">No material inward entries match this filter.</td></tr>
            @endforelse
        </tbody>
        @if ($entries->isNotEmpty())
            <tfoot>
                <tr class="totals-row">
                    <td colspan="5">Total</td>
                    <td class="text-right">Rs. {{ number_format($entries->sum('amount'), 2) }}</td>
                    <td colspan="4"></td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
