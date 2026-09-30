<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2937; }
        h1 { font-size: 16px; margin: 0 0 4px; text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        table.bordered, table.bordered td, table.bordered th { border: 1px solid #333; }
        td, th { padding: 4px 6px; vertical-align: top; }
        .muted { color: #6b7280; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        .items th { background: #f3f4f6; text-transform: uppercase; font-size: 9px; }
        .no-border td { border: none; padding: 2px 6px; }
    </style>
</head>
<body>
    <h1>Tax Invoice</h1>

    <table class="bordered">
        <tr>
            <td style="width: 55%;">
                <span class="bold">{{ $taxInvoice->company->legal_name ?: $taxInvoice->company->name }}</span><br>
                {{ $taxInvoice->company->address }}<br>
                {{ $taxInvoice->company->city }}@if($taxInvoice->company->city && $taxInvoice->company->country), @endif{{ $taxInvoice->company->country }}<br>
                {{ $taxInvoice->company->taxIdLabel() }} : {{ $taxInvoice->company->taxId() ?: '—' }}
            </td>
            <td>
                <table class="no-border">
                    <tr><td>Invoice No.</td><td class="bold">{{ $taxInvoice->tax_invoice_no }}</td></tr>
                    <tr><td>Dated</td><td>{{ $taxInvoice->document_date->format('d-M-y') }}</td></tr>
                    <tr><td>Delivery Note</td><td>{{ $taxInvoice->delivery_note ?: '—' }}</td></tr>
                    <tr><td>Mode/Terms of Payment</td><td>{{ $taxInvoice->payment_terms ?: '—' }}</td></tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <span class="bold">Buyer</span><br>
                {{ $taxInvoice->client->name }}<br>
                {{ $taxInvoice->client->address }}<br>
                Country : {{ $taxInvoice->client->state ?: $taxInvoice->company->country }}<br>
                {{ $taxInvoice->company->taxIdLabel() }} : {{ $taxInvoice->client->gstin ?: '—' }}
            </td>
            <td>
                <table class="no-border">
                    <tr><td>Supplier's Ref.</td><td>{{ $taxInvoice->supplier_ref ?: '—' }}</td></tr>
                    <tr><td>Other Reference(s)</td><td>{{ $taxInvoice->other_reference ?: '—' }}</td></tr>
                    <tr><td>Buyer's Order No.</td><td>{{ $taxInvoice->buyer_order_no ?: '—' }}</td></tr>
                    <tr><td>Dated</td><td>{{ optional($taxInvoice->buyer_order_date)->format('d-M-y') ?? '—' }}</td></tr>
                    <tr><td>Despatch Doc No.</td><td>{{ $taxInvoice->dispatch_doc_no ?: '—' }}</td></tr>
                    <tr><td>Despatched through</td><td>{{ $taxInvoice->dispatch_through ?: '—' }}</td></tr>
                    <tr><td>Destination</td><td>{{ $taxInvoice->destination ?: '—' }}</td></tr>
                </table>
            </td>
        </tr>
        @if ($taxInvoice->terms_of_delivery)
            <tr><td colspan="2"><span class="bold">Terms of Delivery</span><br>{{ $taxInvoice->terms_of_delivery }}</td></tr>
        @endif
    </table>

    <table class="bordered items" style="margin-top: -1px;">
        <thead>
            <tr>
                <th style="width: 5%;">SI No.</th>
                <th>Description of Goods</th>
                <th style="width: 8%;">HSN/SAC</th>
                <th style="width: 10%;" class="text-right">Quantity</th>
                <th style="width: 10%;" class="text-right">Rate</th>
                <th style="width: 6%;">per</th>
                <th style="width: 8%;" class="text-right">{{ $taxInvoice->company->taxLabel() }} %</th>
                <th style="width: 13%;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($taxInvoice->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="bold">{{ $item->name }}</td>
                    <td>{{ $item->hsn_sac_code ?: '—' }}</td>
                    <td class="text-right">{{ rtrim(rtrim($item->quantity, '0'), '.') }} {{ $item->unit }}</td>
                    <td class="text-right">{{ number_format($item->rate, 3) }}</td>
                    <td>{{ $item->unit }}</td>
                    <td class="text-right">{{ $taxInvoice->tax_percent }} %</td>
                    <td class="text-right bold">{{ number_format($item->total, 3) }}</td>
                </tr>
            @endforeach
            @for ($i = 0; $i < max(0, 3 - $taxInvoice->items->count()); $i++)
                <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            @endfor
        </tbody>
    </table>

    <table class="bordered" style="margin-top: -1px;">
        <tr class="no-border">
            <td style="width: 60%;">
                <span class="muted">Amount Chargeable (in words)</span><br>
                <span class="bold">{{ $taxInvoice->company->amountInWords($taxInvoice->total_amount) }}</span>
            </td>
            <td>
                <table class="no-border">
                    <tr><td>Taxable Value</td><td class="text-right">{{ number_format($taxInvoice->subtotal, 3) }}</td></tr>
                    <tr><td>{{ $taxInvoice->company->taxLabel() }} {{ $taxInvoice->tax_percent }}%</td><td class="text-right">{{ number_format($taxInvoice->tax_amount, 3) }}</td></tr>
                    <tr class="bold"><td>Invoice Total</td><td class="text-right">{{ number_format($taxInvoice->total_amount, 3) }}</td></tr>
                </table>
            </td>
        </tr>
        <tr class="no-border">
            <td colspan="2">
                <span class="muted">{{ $taxInvoice->company->taxLabel() }} Amount (in words)</span><br>
                <span class="bold">{{ $taxInvoice->company->amountInWords($taxInvoice->tax_amount) }}</span>
            </td>
        </tr>
    </table>

    <table class="bordered" style="margin-top: -1px;">
        <tr>
            <td style="width: 60%;">
                <span class="bold">Declaration</span><br>
                {{ $taxInvoice->notes ?: 'We declare that this invoice shows the actual price of the goods described and that all particulars are true and correct.' }}
            </td>
            <td class="text-right">
                for {{ $taxInvoice->company->legal_name ?: $taxInvoice->company->name }}
                <div style="margin-top: 40px;">Authorised Signatory</div>
            </td>
        </tr>
    </table>

    <p class="muted" style="text-align: center; margin-top: 8px;">This is a Computer Generated Invoice</p>
</body>
</html>
