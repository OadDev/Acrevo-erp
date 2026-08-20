<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPayment;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PayrollController extends Controller
{
    public function index(Request $request): View
    {
        return $this->indexForScope($request, 'worker');
    }

    public function employeeIndex(Request $request): View
    {
        return $this->indexForScope($request, 'employee');
    }

    /**
     * WO Workers Payroll (attendance from a work order's Measurement Book)
     * and Employee Payroll (attendance from HR > Attendance, for
     * Sales/HR/Finance/Executive Team Leader/QC Officer) are the same page
     * and the same generate/pay/PDF mechanics underneath - only which
     * employees they cover differs, via Employee::workOrderWorkers()/staff().
     */
    private function indexForScope(Request $request, string $scope): View
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        $employees = Employee::where('status', 'active')
            ->when($scope === 'worker', fn ($q) => $q->workOrderWorkers(), fn ($q) => $q->staff())
            ->orderBy('name')
            ->get();

        // Scoped by the employee's role, not by $employees above - a removed
        // (soft-deleted) employee's already-generated payroll must keep
        // showing here, the same as it does everywhere else historical
        // Payroll/Attendance records reference a withTrashed() employee.
        $payrolls = Payroll::with(['employee', 'payments'])
            ->where('month', $month)->where('year', $year)
            ->whereHas('employee', fn ($q) => $scope === 'worker' ? $q->workOrderWorkers() : $q->staff())
            ->get();

        $attendanceByEmployee = Attendance::whereMonth('date', $month)
            ->whereYear('date', $year)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->with('workOrder')
            ->orderBy('date')
            ->get()
            ->groupBy('employee_id');

        return view('payroll.index', compact('payrolls', 'employees', 'month', 'year', 'attendanceByEmployee', 'scope'));
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

        $paidAmount = (float) (Payroll::where('employee_id', $data['employee_id'])
            ->where('month', $data['month'])->where('year', $data['year'])
            ->value('paid_amount') ?? 0);

        Payroll::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'month' => $data['month'], 'year' => $data['year']],
            $data + ['net_salary' => $net, 'status' => $this->statusFor($net, $paidAmount), 'processed_by' => $request->user()->id]
        );

        return back()->with('success', 'Payroll entry saved.');
    }

    public function recordPayment(Request $request, Payroll $payroll): RedirectResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max($payroll->remaining(), 0.01)],
            'paid_on' => ['nullable', 'date'],
        ]);

        $paidAmount = (float) $payroll->paid_amount + $data['amount'];
        $status = $this->statusFor((float) $payroll->net_salary, $paidAmount);

        $payroll->update([
            'paid_amount' => $paidAmount,
            'status' => $status,
            'paid_at' => $status === 'paid' ? now() : $payroll->paid_at,
        ]);

        PayrollPayment::create([
            'payroll_id' => $payroll->id,
            'amount' => $data['amount'],
            'paid_on' => $data['paid_on'] ?? now()->toDateString(),
            'paid_by' => $request->user()->id,
        ]);

        return back()->with('success', $status === 'paid' ? 'Payroll fully paid.' : 'Payment recorded — remaining balance held.');
    }

    private function statusFor(float $net, float $paidAmount): string
    {
        if ($paidAmount <= 0) {
            return 'pending';
        }

        return $paidAmount >= $net ? 'paid' : 'partial';
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
        $net = $basicSalary - $advanceDeducted;

        $paidAmount = (float) (Payroll::where('employee_id', $data['employee_id'])
            ->where('month', $data['month'])->where('year', $data['year'])
            ->value('paid_amount') ?? 0);

        Payroll::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'month' => $data['month'], 'year' => $data['year']],
            [
                'basic_salary' => $basicSalary,
                'allowances' => 0,
                'deductions' => 0,
                'advance_deducted' => $advanceDeducted,
                'overtime_amount' => 0,
                'incentive' => 0,
                'net_salary' => $net,
                'status' => $this->statusFor($net, $paidAmount),
                'processed_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Payroll generated from attendance.');
    }

    public function pdf(Payroll $payroll)
    {
        $payroll->load('employee', 'payments');

        $pdf = Pdf::loadView('payroll.pdf', compact('payroll'));

        $filename = $payroll->employee->name.'-'.\Carbon\Carbon::create($payroll->year, $payroll->month, 1)->format('M-Y').'.pdf';

        return $pdf->download($filename);
    }
}
