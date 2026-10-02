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
        {{ $title }} — {{ $workOrder->work_order_no }}
        @if ($from || $to)
            — {{ $from ? \Carbon\Carbon::parse($from)->format('d M Y') : 'Start' }} to {{ $to ? \Carbon\Carbon::parse($to)->format('d M Y') : 'Today' }}
        @endif
    </p>

    <table>
        <thead>
            <tr>
                <th>Date</th>
                <th>Category</th>
                <th>Description</th>
                <th class="text-right">Borrow</th>
                <th class="text-right">Credit</th>
                <th class="text-right">Debit</th>
                <th class="text-right">Lended</th>
                <th class="text-right">Balance</th>
                <th>Remark</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr>
                    <td>{{ $entry->entry_date->format('d M Y') }}</td>
                    <td>{{ $entry->category ?? '—' }}</td>
                    <td>{{ $entry->description ?? '—' }}</td>
                    <td class="text-right">{{ $entry->type === 'borrow' ? 'Rs. '.number_format($entry->amount, 2) : '—' }}</td>
                    <td class="text-right">{{ $entry->type === 'credit' ? 'Rs. '.number_format($entry->amount, 2) : '—' }}</td>
                    <td class="text-right">{{ $entry->type === 'debit' ? 'Rs. '.number_format($entry->amount, 2) : '—' }}</td>
                    <td class="text-right">{{ $entry->type === 'lended' ? 'Rs. '.number_format($entry->amount, 2) : '—' }}</td>
                    <td class="text-right">Rs. {{ number_format($entry->balance, 2) }}</td>
                    <td>{{ $entry->remark ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9">No ledger entries match this filter.</td></tr>
            @endforelse
        </tbody>
        @if ($entries->isNotEmpty())
            <tfoot>
                <tr class="totals-row">
                    <td colspan="3">Totals</td>
                    <td class="text-right">Rs. {{ number_format($entries->where('type', 'borrow')->sum('amount'), 2) }}</td>
                    <td class="text-right">Rs. {{ number_format($entries->where('type', 'credit')->sum('amount'), 2) }}</td>
                    <td class="text-right">Rs. {{ number_format($entries->where('type', 'debit')->sum('amount'), 2) }}</td>
                    <td class="text-right">Rs. {{ number_format($entries->where('type', 'lended')->sum('amount'), 2) }}</td>
                    <td class="text-right">Rs. {{ number_format($entries->last()->balance, 2) }}</td>
                    <td></td>
                </tr>
            </tfoot>
        @endif
    </table>
</body>
</html>
