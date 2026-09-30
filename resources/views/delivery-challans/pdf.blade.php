<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 18px; margin: 0; text-align: center; background: #e8b4c8; padding: 8px; }
        table { width: 100%; border-collapse: collapse; }
        table.bordered, table.bordered td, table.bordered th { border: 1px solid #333; }
        td, th { padding: 5px 8px; vertical-align: top; }
        .muted { color: #6b7280; }
        .bold { font-weight: bold; }
        .items th { background: #f3d9e3; text-transform: none; }
        .section-title { background: #f3d9e3; font-weight: bold; padding: 5px 8px; }
    </style>
</head>
<body>
    <h1>Delivery Challan</h1>

    <table class="bordered" style="margin-top: -1px;">
        <tr>
            <td style="width: 60%;">
                <span class="bold">{{ $deliveryChallan->company->legal_name ?: $deliveryChallan->company->name }}</span><br>
                {{ $deliveryChallan->company->address }}<br>
                Phone: {{ $deliveryChallan->company->phone }}<br>
                Email: {{ $deliveryChallan->company->email }}<br>
                {{ $deliveryChallan->company->taxIdLabel() }}: {{ $deliveryChallan->company->taxId() }}
            </td>
            <td class="muted" style="text-align: center; vertical-align: middle;">Logo</td>
        </tr>
    </table>

    <table class="bordered" style="margin-top: -1px;">
        <tr>
            <td class="section-title" style="width: 50%;">Delivery Challan For:</td>
            <td class="section-title">Shipping To:</td>
        </tr>
        <tr>
            <td>
                <span class="bold">{{ $deliveryChallan->client->name }}</span><br>
                {{ $deliveryChallan->client->address }}<br>
                Phone No.: {{ $deliveryChallan->client->phone }}<br>
                Email: {{ $deliveryChallan->client->email }}<br>
                {{ $deliveryChallan->company->taxIdLabel() }}: {{ $deliveryChallan->client->gstin ?: '—' }}
            </td>
            <td>
                <span class="bold">{{ $deliveryChallan->shipping_name ?: $deliveryChallan->client->name }}</span><br>
                {{ $deliveryChallan->shipping_address ?: $deliveryChallan->client->address }}<br>
                Phone No.: {{ $deliveryChallan->shipping_phone ?: $deliveryChallan->client->phone }}<br>
                Email: {{ $deliveryChallan->shipping_email ?: $deliveryChallan->client->email }}<br>
                {{ $deliveryChallan->company->taxIdLabel() }}: {{ $deliveryChallan->shipping_tax_id ?: '—' }}
            </td>
        </tr>
        <tr>
            <td>Challan No.: <span class="bold">{{ $deliveryChallan->challan_no }}</span></td>
            <td>Delivery time: {{ $deliveryChallan->delivery_time ?: '—' }}</td>
        </tr>
        <tr>
            <td>Date: {{ $deliveryChallan->challan_date->format('d-M-Y') }}</td>
            <td></td>
        </tr>
    </table>

    <table class="bordered items" style="margin-top: -1px;">
        <thead>
            <tr>
                <th style="width: 8%;">SI No.</th>
                <th>Item Name</th>
                <th style="width: 15%;">HSN/SAC Code</th>
                <th style="width: 12%;">Quantity</th>
                <th style="width: 12%;">Unit</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($deliveryChallan->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td>{{ $item->name }}</td>
                    <td>{{ $item->hsn_sac_code ?: '—' }}</td>
                    <td>{{ rtrim(rtrim($item->quantity, '0'), '.') }}</td>
                    <td>{{ $item->unit }}</td>
                </tr>
            @endforeach
            <tr>
                <td colspan="3" class="section-title" style="text-align: center;">Total</td>
                <td class="bold">{{ rtrim(rtrim((string) $deliveryChallan->items->sum('quantity'), '0'), '.') }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <table class="bordered" style="margin-top: -1px;">
        <tr>
            <td class="section-title" style="width: 50%;">Terms and conditions:</td>
            <td class="section-title">For, {{ $deliveryChallan->company->name }}</td>
        </tr>
        <tr>
            <td style="height: 60px;">{{ $deliveryChallan->terms_and_conditions }}</td>
            <td style="text-align: center; vertical-align: bottom;">Authorised Signature</td>
        </tr>
    </table>

    <table class="bordered" style="margin-top: -1px;">
        <tr><td class="bold">Received By</td></tr>
        <tr><td>Name: {{ $deliveryChallan->received_by_name }}</td></tr>
        <tr><td>Comment: {{ $deliveryChallan->received_by_comment }}</td></tr>
        <tr><td>Date: {{ optional($deliveryChallan->received_by_date)->format('d-M-Y') }}</td></tr>
        <tr><td>Signature:</td></tr>
    </table>

    <table class="bordered" style="margin-top: -1px;">
        <tr><td class="bold">Delivered By</td></tr>
        <tr><td>Name: {{ $deliveryChallan->delivered_by_name }}</td></tr>
        <tr><td>Comment: {{ $deliveryChallan->delivered_by_comment }}</td></tr>
        <tr><td>Date: {{ optional($deliveryChallan->delivered_by_date)->format('d-M-Y') }}</td></tr>
        <tr><td>Signature:</td></tr>
    </table>
</body>
</html>
