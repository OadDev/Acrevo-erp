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
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: bold; }
        .badge-paid { background: #d1fae5; color: #047857; }
        .badge-partial { background: #dbeafe; color: #1d4ed8; }
        .badge-pending { background: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body>
    <h1>{{ config('app.name') }}</h1>
    <p class="muted">Payslip — {{ \Carbon\Carbon::create($payroll->year, $payroll->month, 1)->format('F Y') }}</p>

    <table style="margin-top: 20px; border: none;">
        <tr style="border: none;">
            <td style="border: none; width: 50%;">
                <strong>{{ $payroll->employee->name }}</strong><br>
                {{ $payroll->employee->employee_code }}<br>
                {{ $payroll->employee->designation }}
            </td>
            <td style="border: none; width: 50%;" class="text-right">
                <span class="badge badge-{{ $payroll->status }}">{{ ucfirst($payroll->status) }}</span><br>
                @if ($payroll->paid_at)
                    Fully Paid: {{ $payroll->paid_at->format('d M Y') }}
                @endif
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr><th>Component</th><th class="text-right">Amount</th></tr>
        </thead>
        <tbody>
            <tr><td>Basic Salary</td><td class="text-right">Rs. {{ number_format($payroll->basic_salary, 2) }}</td></tr>
            <tr><td>Allowances</td><td class="text-right">Rs. {{ number_format($payroll->allowances, 2) }}</td></tr>
            <tr><td>Overtime</td><td class="text-right">Rs. {{ number_format($payroll->overtime_amount, 2) }}</td></tr>
            <tr><td>Incentive</td><td class="text-right">Rs. {{ number_format($payroll->incentive, 2) }}</td></tr>
            <tr><td>Deductions</td><td class="text-right">-Rs. {{ number_format($payroll->deductions, 2) }}</td></tr>
            <tr><td>Advance Deducted</td><td class="text-right">-Rs. {{ number_format($payroll->advance_deducted, 2) }}</td></tr>
        </tbody>
    </table>

    <table class="totals">
        <tr class="grand"><td>Net Salary</td><td class="text-right">Rs. {{ number_format($payroll->net_salary, 2) }}</td></tr>
        <tr><td>Paid So Far</td><td class="text-right">Rs. {{ number_format($payroll->paid_amount, 2) }}</td></tr>
        <tr><td>Held / Remaining</td><td class="text-right">Rs. {{ number_format($payroll->remaining(), 2) }}</td></tr>
    </table>

    @if ($payroll->payments->isNotEmpty())
        <table>
            <thead>
                <tr><th>Payment Date</th><th class="text-right">Amount Paid</th></tr>
            </thead>
            <tbody>
                @foreach ($payroll->payments as $payment)
                    <tr>
                        <td>{{ $payment->paid_on->format('d M Y') }}</td>
                        <td class="text-right">Rs. {{ number_format($payment->amount, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
