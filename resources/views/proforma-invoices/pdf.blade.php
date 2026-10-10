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
    <h1>Proforma Invoice</h1>

    <table class="bordered">
        <tr>
            <td style="width: 55%;">
                <table class="no-border">
                    <tr>
                        <td style="width: 70px; padding: 0;">
                            @if ($logoDataUri = $proformaInvoice->company->logoDataUri())
                                <img src="{{ $logoDataUri }}" style="max-height: 55px; max-width: 65px;">
                            @else
                                <span class="muted">Logo</span>
                            @endif
                        </td>
                        <td style="padding: 0;">
                            <span class="bold">{{ $proformaInvoice->company->legal_name ?: $proformaInvoice->company->name }}</span><br>
                            {{ $proformaInvoice->company->address }}<br>
                            {{ $proformaInvoice->company->city }}@if($proformaInvoice->company->city && $proformaInvoice->company->country), @endif{{ $proformaInvoice->company->country }}<br>
                            {{ $proformaInvoice->company->taxIdLabel() }} : {{ $proformaInvoice->company->taxId() ?: '—' }}
                        </td>
                    </tr>
                </table>
            </td>
            <td>
                <table class="no-border">
                    <tr><td>Proforma No.</td><td class="bold">{{ $proformaInvoice->proforma_no }}</td></tr>
                    <tr><td>Dated</td><td>{{ $proformaInvoice->document_date->format('d-M-y') }}</td></tr>
                    <tr><td>Delivery Note</td><td>{{ $proformaInvoice->delivery_note ?: '—' }}</td></tr>
                    <tr><td>Mode/Terms of Payment</td><td>{{ $proformaInvoice->payment_terms ?: '—' }}</td></tr>
                </table>
            </td>
        </tr>
        <tr>
            <td>
                <span class="bold">Buyer</span><br>
                {{ $proformaInvoice->client->name }}<br>
                {{ $proformaInvoice->client->address }}<br>
                Country : {{ $proformaInvoice->client->state ?: $proformaInvoice->company->country }}<br>
                {{ $proformaInvoice->company->taxIdLabel() }} : {{ $proformaInvoice->client->gstin ?: '—' }}
            </td>
            <td>
                <table class="no-border">
                    <tr><td>Supplier's Ref.</td><td>{{ $proformaInvoice->supplier_ref ?: '—' }}</td></tr>
                    <tr><td>Other Reference(s)</td><td>{{ $proformaInvoice->other_reference ?: '—' }}</td></tr>
                    <tr><td>Buyer's Order No.</td><td>{{ $proformaInvoice->buyer_order_no ?: '—' }}</td></tr>
                    <tr><td>Dated</td><td>{{ optional($proformaInvoice->buyer_order_date)->format('d-M-y') ?? '—' }}</td></tr>
                    <tr><td>Despatch Doc No.</td><td>{{ $proformaInvoice->dispatch_doc_no ?: '—' }}</td></tr>
                    <tr><td>Despatched through</td><td>{{ $proformaInvoice->dispatch_through ?: '—' }}</td></tr>
                    <tr><td>Destination</td><td>{{ $proformaInvoice->destination ?: '—' }}</td></tr>
                </table>
            </td>
        </tr>
        @if ($proformaInvoice->terms_of_delivery)
            <tr><td colspan="2"><span class="bold">Terms of Delivery</span><br>{{ $proformaInvoice->terms_of_delivery }}</td></tr>
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
                <th style="width: 8%;" class="text-right">{{ $proformaInvoice->company->taxLabel() }} %</th>
                <th style="width: 13%;" class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($proformaInvoice->items as $item)
                <tr>
                    <td>{{ $loop->iteration }}</td>
                    <td class="bold">{{ $item->name }}</td>
                    <td>{{ $item->hsn_sac_code ?: '—' }}</td>
                    <td class="text-right">{{ rtrim(rtrim($item->quantity, '0'), '.') }} {{ $item->unit }}</td>
                    <td class="text-right">{{ number_format($item->rate, 3) }}</td>
                    <td>{{ $item->unit }}</td>
                    <td class="text-right">{{ $proformaInvoice->tax_percent }} %</td>
                    <td class="text-right bold">{{ number_format($item->total, 3) }}</td>
                </tr>
            @endforeach
            @for ($i = 0; $i < max(0, 3 - $proformaInvoice->items->count()); $i++)
                <tr><td>&nbsp;</td><td></td><td></td><td></td><td></td><td></td><td></td><td></td></tr>
            @endfor
        </tbody>
    </table>

    <table class="bordered" style="margin-top: -1px;">
        <tr class="no-border">
            <td style="width: 60%;">
                <span class="muted">Amount Chargeable (in words)</span><br>
                <span class="bold">{{ $proformaInvoice->company->amountInWords($proformaInvoice->total_amount) }}</span>
            </td>
            <td>
                <table class="no-border">
                    <tr><td>Taxable Value</td><td class="text-right">{{ number_format($proformaInvoice->subtotal, 3) }}</td></tr>
                    <tr><td>{{ $proformaInvoice->company->taxLabel() }} {{ $proformaInvoice->tax_percent }}%</td><td class="text-right">{{ number_format($proformaInvoice->tax_amount, 3) }}</td></tr>
                    <tr class="bold"><td>{{ $proformaInvoice->company->currency }} Total</td><td class="text-right">{{ number_format($proformaInvoice->total_amount, 3) }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    <table class="bordered" style="margin-top: -1px;">
        <tr>
            <td style="width: 60%;">
                <span class="bold">Declaration</span><br>
                {{ $proformaInvoice->notes ?: 'We declare that this proforma invoice shows the actual price of the goods described and that all particulars are true and correct.' }}
            </td>
            <td class="text-right">
                for {{ $proformaInvoice->company->legal_name ?: $proformaInvoice->company->name }}
                <div style="margin-top: 40px;">Authorised Signatory</div>
            </td>
        </tr>
    </table>

    <p class="muted" style="text-align: center; margin-top: 8px;">This is a Computer Generated Proforma Invoice</p>
</body>
</html>
