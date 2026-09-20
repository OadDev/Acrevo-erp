<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('work-orders.pdf._styles')
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Client Payments</p>
    <p class="muted">
        @if ($client ?? null) Client: {{ $client->name }} &middot; @endif
        @if ($filters['from'] ?? null) From: {{ \Carbon\Carbon::parse($filters['from'])->format('d M Y') }} &middot; @endif
        @if ($filters['to'] ?? null) To: {{ \Carbon\Carbon::parse($filters['to'])->format('d M Y') }} &middot; @endif
        Generated {{ now()->timezone('Asia/Kolkata')->format('d-M-Y, h:i A') }} IST
    </p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Client</th>
                <th>Site</th>
                <th>Work Order</th>
                <th>Mode</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($payments as $payment)
                <tr>
                    <td>{{ $payment->payment_date->format('d M Y') }}</td>
                    <td>{{ $payment->client?->name ?? '—' }}</td>
                    <td>{{ $payment->site?->site_no ?? '—' }}</td>
                    <td>{{ $payment->workOrder?->work_order_no ?? '—' }}</td>
                    <td>{{ Str::title(str_replace('_', ' ', $payment->mode)) }}</td>
                    <td class="text-right">Rs. {{ number_format($payment->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No payments match this filter.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
