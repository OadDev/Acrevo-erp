<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
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

        $attendanceByEmployee = Attendance::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->with('workOrder')
            ->orderBy('date')
            ->get()
            ->groupBy('employee_id');

        return view('payroll.index', compact('payrolls', 'employees', 'month', 'year', 'attendanceByEmployee'));
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

    public function generateFromAttendance(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'month' => ['required', 'integer', 'min:1', 'max:12'],
            'year' => ['required', 'integer', 'min:2000'],
        ]);

        $attendance = Attendance::where('employee_id', $data['employee_id'])
            ->whereMonth('date', $data['month'])
            ->whereYear('date', $data['year'])
            ->get();

        $basicSalary = (float) $attendance->sum('salary');
        $advanceDeducted = (float) $attendance->sum('advance');

        Payroll::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'month' => $data['month'], 'year' => $data['year']],
            [
                'basic_salary' => $basicSalary,
                'allowances' => 0,
                'deductions' => 0,
                'advance_deducted' => $advanceDeducted,
                'overtime_amount' => 0,
                'incentive' => 0,
                'net_salary' => $basicSalary - $advanceDeducted,
                'status' => 'pending',
                'processed_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Payroll generated from attendance.');
    }
}
