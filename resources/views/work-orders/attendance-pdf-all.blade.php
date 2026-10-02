<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        h2 { font-size: 13px; margin: 18px 0 4px; color: #1f2937; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 10px; color: #6b7280; }
        .text-right { text-align: right; }
        .subtotal { font-weight: bold; background: #f9fafb; }
        .worker-block { page-break-inside: avoid; }
        .grand-table { width: 260px; margin-left: auto; margin-top: 20px; }
        .grand-table td { border: none; padding: 4px 8px; }
        .grand { font-weight: bold; font-size: 14px; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Worker Attendance — {{ $workOrder->work_order_no }} — {{ \Carbon\Carbon::parse($from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>

    @forelse ($records as $entries)
        @php $employee = $entries->first()->employee; @endphp
        <div class="worker-block">
            <h2>
                {{ $employee?->name ?? 'Unknown Worker' }}
                @if ($employee?->employee_code)
                    <span class="muted">({{ $employee->employee_code }})</span>
                @endif
            </h2>
            <table>
                <thead>
                    <tr>
                        <th>Date</th><th>Status</th><th>In</th><th>Out</th><th>Break</th>
                        <th class="text-right">Hours</th><th class="text-right">Salary</th><th class="text-right">Advance</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($entries as $record)
                        <tr>
                            <td>{{ $record->date->format('d M Y') }}</td>
                            <td>{{ ucfirst(str_replace('_', ' ', $record->status)) }}</td>
                            <td>{{ $record->check_in ?? '—' }}</td>
                            <td>{{ $record->check_out ?? '—' }}</td>
                            <td>{{ $record->break_minutes ? $record->break_minutes.' min' : '—' }}</td>
                            <td class="text-right">{{ $record->hours_worked ?? '—' }}</td>
                            <td class="text-right">{{ $record->salary ? 'Rs. '.number_format($record->salary, 2) : '—' }}</td>
                            <td class="text-right">{{ $record->advance ? 'Rs. '.number_format($record->advance, 2) : '—' }}</td>
                        </tr>
                    @endforeach
                    <tr class="subtotal">
                        <td colspan="5">
                            Present: {{ $entries->where('status', 'present')->count() }}
                            &middot; Half Day: {{ $entries->where('status', 'half_day')->count() }}
                            &middot; Absent: {{ $entries->where('status', 'absent')->count() }}
                            &middot; Leave: {{ $entries->where('status', 'leave')->count() }}
                        </td>
                        <td class="text-right">{{ $entries->sum('hours_worked') }}</td>
                        <td class="text-right">Rs. {{ number_format($entries->sum('salary'), 2) }}</td>
                        <td class="text-right">Rs. {{ number_format($entries->sum('advance'), 2) }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    @empty
        <p class="muted">No attendance records for this range.</p>
    @endforelse

    @if ($records->isNotEmpty())
        <table class="grand-table">
            <tr class="grand"><td>Total Salary</td><td class="text-right">Rs. {{ number_format($records->collapse()->sum('salary'), 2) }}</td></tr>
            <tr><td>Total Advance</td><td class="text-right">Rs. {{ number_format($records->collapse()->sum('advance'), 2) }}</td></tr>
        </table>
    @endif
</body>
</html>
