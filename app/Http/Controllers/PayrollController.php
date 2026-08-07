<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Payroll;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        $payrolls = Payroll::with('employee')
            ->where('month', $month)->where('year', $year)
            ->get();

        $employees = Employee::where('status', 'active')->orderBy('name')->get();

        return view('payroll.index', compact('payrolls', 'employees', 'month', 'year'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000'],
            'basic_salary' => ['required', 'numeric', 'min:0'],
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
            'advance_deducted' => ['nullable', 'numeric', 'min:0'],
            'overtime_amount' => ['nullable', 'numeric', 'min:0'],
            'incentive' => ['nullable', 'numeric', 'min:0'],
        ]);

        $net = $data['basic_salary']
            + ($data['allowances'] ?? 0)
            + ($data['overtime_amount'] ?? 0)
            + ($data['incentive'] ?? 0)
            - ($data['deductions'] ?? 0)
            - ($data['advance_deducted'] ?? 0);

        Payroll::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'month' => $data['month'], 'year' => $data['year']],
            $data + ['net_salary' => $net, 'status' => 'pending', 'processed_by' => $request->user()->id]
        );

        return back()->with('success', 'Payroll entry saved.');
    }

    public function markPaid(Payroll $payroll): RedirectResponse
    {
        $payroll->update(['status' => 'paid', 'paid_at' => now()]);

        return back()->with('success', 'Payroll marked as paid.');
    }
}
