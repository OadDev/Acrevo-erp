<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('work-orders.pdf._styles')
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Invoices</p>
    <p class="muted">Generated {{ now()->timezone('Asia/Kolkata')->format('d-M-Y, h:i A') }} IST</p>

    <table>
        <thead>
            <tr>
                <th>Invoice No</th>
                <th>Client</th>
                <th>Work Order</th>
                <th class="text-right">Amount</th>
                <th class="text-right">Paid</th>
                <th class="text-right">Due</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($invoices as $invoice)
                <tr>
                    <td>{{ $invoice->invoice_no }}</td>
                    <td>{{ $invoice->client?->name ?? '—' }}</td>
                    <td>{{ $invoice->workOrder?->work_order_no ?? '—' }}</td>
                    <td class="text-right">Rs. {{ number_format($invoice->total_amount, 2) }}</td>
                    <td class="text-right">Rs. {{ number_format($invoice->paidAmount(), 2) }}</td>
                    <td class="text-right">Rs. {{ number_format($invoice->balanceDue(), 2) }}</td>
                    <td>{{ Str::title($invoice->status) }}</td>
                </tr>
            @empty
                <tr><td colspan="7">No invoices yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
