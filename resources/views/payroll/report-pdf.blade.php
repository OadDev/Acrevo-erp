<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        h2 { font-size: 14px; margin: 18px 0 4px; color: #374151; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 10px; color: #6b7280; }
        .text-right { text-align: right; }
        .totals { width: 280px; margin-left: auto; margin-top: 12px; }
        .totals td { border: none; padding: 4px 8px; }
        .grand { font-weight: bold; font-size: 14px; border-top: 1px solid #e5e7eb; }
        .wo-subtotal td { font-weight: bold; background: #f9fafb; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">
        WO Workers Payroll Report — {{ \Carbon\Carbon::parse($reportFrom)->format('d M Y') }} to {{ \Carbon\Carbon::parse($reportTo)->format('d M Y') }}
        @if ($workOrderFilter)
            — {{ $workOrderFilter->work_order_no }}
        @endif
    </p>

    @if ($singleWorker)
        <table style="margin-top: 20px; border: none;">
            <tr style="border: none;">
                <td style="border: none; width: 100%;">
                    <strong>{{ $singleWorker->employee?->name ?? 'Removed Worker' }}</strong><br>
                    {{ $singleWorker->employee?->employee_code }}<br>
                    {{ $singleWorker->employee?->designation }}
                </td>
            </tr>
        </table>

        @foreach ($singleWorker->byWorkOrder as $wo)
            <h2>{{ $wo->workOrder?->work_order_no ?? 'Removed Work Order' }}</h2>
            <table>
                <thead>
                    <tr><th>Date</th><th>Status</th><th>In</th><th>Out</th><th class="text-right">Hours</th><th class="text-right">Salary</th><th class="text-right">Advance</th></tr>
                </thead>
                <tbody>
                    @foreach ($wo->records as $record)
                        <tr>
                            <td>{{ $record->date->format('d M Y') }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $record->status)) }}</td>
                            <td>{{ $record->check_in ?? '—' }}</td>
                            <td>{{ $record->check_out ?? '—' }}</td>
                            <td class="text-right">{{ $record->hours_worked ?? '—' }}</td>
                            <td class="text-right">{{ $record->salary ? 'Rs. '.number_format($record->salary, 2) : '—' }}</td>
                            <td class="text-right">{{ $record->advance ? 'Rs. '.number_format($record->advance, 2) : '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="wo-subtotal">
                        <td colspan="5">Subtotal — {{ $wo->daysPresent }} day(s)</td>
                        <td class="text-right">Rs. {{ number_format($wo->totalSalary, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($wo->totalAdvance, 2) }}</td>
                    </tr>
                </tbody>
            </table>
        @endforeach

        <table class="totals">
            <tr><td>Total Days Present</td><td class="text-right">{{ $singleWorker->daysPresent }}</td></tr>
            <tr class="grand"><td>Total Salary (All Work Orders)</td><td class="text-right">Rs. {{ number_format($singleWorker->totalSalary, 2) }}</td></tr>
            <tr><td>Total Advance</td><td class="text-right">Rs. {{ number_format($singleWorker->totalAdvance, 2) }}</td></tr>
        </table>
    @else
        <table>
            <thead>
                <tr><th>Worker</th><th>Work Order(s)</th><th class="text-right">Days Present</th><th class="text-right">Total Salary</th><th class="text-right">Total Advance</th></tr>
            </thead>
            <tbody>
                @forelse ($reportByWorker as $row)
                    <tr>
                        <td>{{ $row->employee?->name ?? 'Removed Worker' }}</td>
                        <td>{{ $row->byWorkOrder->map(fn ($wo) => $wo->workOrder?->work_order_no ?? '—')->implode(', ') }}</td>
                        <td class="text-right">{{ $row->daysPresent }}</td>
                        <td class="text-right">Rs. {{ number_format($row->totalSalary, 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($row->totalAdvance, 2) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5">No attendance matches this filter.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr class="grand"><td>Grand Total Salary</td><td class="text-right">Rs. {{ number_format($reportGrandTotalSalary, 2) }}</td></tr>
            <tr><td>Grand Total Advance</td><td class="text-right">Rs. {{ number_format($reportGrandTotalAdvance, 2) }}</td></tr>
        </table>
    @endif
</body>
</html>
