<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; margin-top: 16px; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 10px; color: #6b7280; }
        .text-right { text-align: right; }
        .totals { width: 260px; margin-left: auto; margin-top: 12px; }
        .totals td { border: none; padding: 4px 8px; }
        .grand { font-weight: bold; font-size: 14px; border-top: 1px solid #e5e7eb; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Worker Attendance — {{ $workOrder->work_order_no }} — {{ \Carbon\Carbon::parse($from)->format('d M Y') }} to {{ \Carbon\Carbon::parse($to)->format('d M Y') }}</p>

    <table style="margin-top: 20px; border: none;">
        <tr style="border: none;">
            <td style="border: none; width: 100%;">
                <strong>{{ $employee->name }}</strong><br>
                {{ $employee->employee_code }}<br>
                {{ $employee->designation }}
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr><th>Date</th><th>Status</th><th>In</th><th>Out</th><th>Break</th><th class="text-right">Hours</th><th class="text-right">Salary</th><th class="text-right">Advance</th></tr>
        </thead>
        <tbody>
            @forelse ($records as $record)
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
            @empty
                <tr><td colspan="8">No attendance records for this range.</td></tr>
            @endforelse
        </tbody>
    </table>

    <table class="totals">
        <tr><td>Days Present</td><td class="text-right">{{ $records->where('status', 'present')->count() }}</td></tr>
        <tr><td>Half Day</td><td class="text-right">{{ $records->where('status', 'half_day')->count() }}</td></tr>
        <tr><td>Absent</td><td class="text-right">{{ $records->where('status', 'absent')->count() }}</td></tr>
        <tr><td>Leave</td><td class="text-right">{{ $records->where('status', 'leave')->count() }}</td></tr>
        <tr class="grand"><td>Total Salary</td><td class="text-right">Rs. {{ number_format($records->sum('salary'), 2) }}</td></tr>
        <tr><td>Total Advance</td><td class="text-right">Rs. {{ number_format($records->sum('advance'), 2) }}</td></tr>
    </table>
</body>
</html>
