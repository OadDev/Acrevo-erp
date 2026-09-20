<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @include('work-orders.pdf._styles')
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Expenses &mdash; General company expenses not tied to a work order</p>
    <p class="muted">
        @if ($filters['from'] ?? null) From: {{ \Carbon\Carbon::parse($filters['from'])->format('d M Y') }} &middot; @endif
        @if ($filters['to'] ?? null) To: {{ \Carbon\Carbon::parse($filters['to'])->format('d M Y') }} &middot; @endif
        @if ($filters['category'] ?? null) Category: {{ $filters['category'] }} &middot; @endif
        @if ($filters['type'] ?? null) Type: {{ Str::title($filters['type']) }} &middot; @endif
        @if ($workOrder ?? null) Work Order: {{ $workOrder->work_order_no }} &middot; @endif
        Generated {{ now()->timezone('Asia/Kolkata')->format('d-M-Y, h:i A') }} IST
    </p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Description</th>
                <th>Work Order</th>
                <th class="text-right">Borrow</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Lended</th>
                <th class="text-right">Balance</th>
                <th>Remark</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($expenses as $expense)
                <tr>
                    <td>{{ $expense->expense_date->format('d M Y') }}</td>
                    <td>{{ $expense->category }}</td>
                    <td>{{ $expense->description }}</td>
                    <td>{{ $expense->workOrder?->work_order_no ?? '—' }}</td>
                    <td class="text-right">{{ $expense->type === 'borrow' ? 'Rs. '.number_format($expense->amount, 2) : '—' }}</td>
                    <td class="text-right">{{ $expense->type === 'credit' ? 'Rs. '.number_format($expense->amount, 2) : '—' }}</td>
                    <td class="text-right">{{ $expense->type === 'debit' ? 'Rs. '.number_format($expense->amount, 2) : '—' }}</td>
                    <td class="text-right">{{ $expense->type === 'lended' ? 'Rs. '.number_format($expense->amount, 2) : '—' }}</td>
                    <td class="text-right">Rs. {{ number_format($expense->balance, 2) }}</td>
                    <td>{{ $expense->remark }}</td>
                </tr>
                @if ($expense->getFirstMedia('bill'))
                    <tr><td></td><td colspan="9">@include('work-orders.pdf._media', ['media' => $expense->getFirstMedia('bill'), 'label' => 'Bill'])</td></tr>
                @endif
            @empty
                <tr><td colspan="10">No expenses match this filter.</td></tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
