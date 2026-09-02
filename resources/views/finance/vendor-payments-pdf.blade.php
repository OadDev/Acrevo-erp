<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('work-orders.pdf._styles')
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Sub Contractor Payments</p>
    <p class="muted">
        @if ($filters['from'] ?? null) From: {{ \Carbon\Carbon::parse($filters['from'])->format('d M Y') }} &middot; @endif
        @if ($filters['to'] ?? null) To: {{ \Carbon\Carbon::parse($filters['to'])->format('d M Y') }} &middot; @endif
        Generated {{ now()->timezone('Asia/Kolkata')->format('d-M-Y, h:i A') }} IST
    </p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Sub Contractor</th>
                <th>Work Order</th>
                <th>Category</th>
                <th class="text-right">Amount</th>
                <th>Remark</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($vendorPayments as $vp)
                <tr>
                    <td>{{ $vp->payment_date->format('d M Y') }}</td>
                    <td>{{ $vp->subContractor?->name ?? $vp->vendor_name }}</td>
                    <td>{{ $vp->workOrder?->work_order_no ?? '—' }}</td>
                    <td>{{ $vp->category ?? '—' }}</td>
                    <td class="text-right">Rs. {{ number_format($vp->amount, 2) }}</td>
                    <td>{{ $vp->remark ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="6">No sub-contractor payments match this filter.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
