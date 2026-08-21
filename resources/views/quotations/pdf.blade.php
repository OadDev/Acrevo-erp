<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('pdf._fonts')
    <style>
        body { font-family: 'DejaVu Sans', 'Noto Sans Tamil', sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 10px; color: #6b7280; }
        .text-right { text-align: right; }
        .totals { width: 260px; margin-left: auto; margin-top: 12px; }
        .totals td { border: none; padding: 4px 8px; }
        .grand { font-weight: bold; font-size: 14px; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Quotation {{ $quotation->quotation_no }} (Version {{ $quotation->version }})</p>

    <table style="margin-top: 20px; border: none;">
        <tr style="border: none;">
            <td style="border: none; width: 50%;">
                <strong>Bill To</strong><br>
                {{ $quotation->client->name }}<br>
                {{ $quotation->client->address }}<br>
                {{ $quotation->client->phone }} · {{ $quotation->client->email }}
            </td>
            <td style="border: none; width: 50%;" class="text-right">
                Date: {{ $quotation->created_at->format('d M Y') }}<br>
                Valid Until: {{ optional($quotation->valid_until)->format('d M Y') ?? '—' }}
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Item</th>
                <th>Unit</th>
                <th>Qty</th>
                <th>Unit Price</th>
                <th class="text-right">Total</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($quotation->items as $item)
                <tr>
                    <td>{{ $item->name }}<br><span class="muted">{{ $item->description }}</span></td>
                    <td>{{ $item->unit }}</td>
                    <td>{{ $item->quantity }}</td>
                    <td>Rs. {{ number_format($item->unit_price, 2) }}</td>
                    <td class="text-right">Rs. {{ number_format($item->total, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Subtotal</td><td class="text-right">Rs. {{ number_format($quotation->subtotal, 2) }}</td></tr>
        <tr><td>Tax ({{ $quotation->tax_percent }}%)</td><td class="text-right">Rs. {{ number_format($quotation->tax_amount, 2) }}</td></tr>
        <tr class="grand"><td>Grand Total</td><td class="text-right">Rs. {{ number_format($quotation->total_amount, 2) }}</td></tr>
    </table>

    @if ($quotation->terms)
        <p style="margin-top: 24px;"><strong>Terms &amp; Conditions</strong><br>{{ $quotation->terms }}</p>
    @endif
</body>
</html>
