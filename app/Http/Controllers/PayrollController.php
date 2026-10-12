<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Payroll;
use App\Models\PayrollPayment;
use App\Models\WorkOrder;
use App\Support\Pdf;
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

        $report = $scope === 'worker' ? $this->workerReport($request, $month, $year) : null;

        return view('payroll.index', compact('payrolls', 'employees', 'month', 'year', 'attendanceByEmployee', 'scope') + ($report ?? []));
    }

    /**
     * Sir asked for the WO Workers Payroll page to stop requiring you to
     * open every work order separately to filter attendance and add up
     * salaries by hand - this is that report. Filterable by Work Order,
     * worker, and an arbitrary date range (defaulting to the month/year
     * selected above when no range is given), with totals computed
     * automatically: every worker's combined total across however many
     * work orders they touched in range, broken down WO-wise, plus a
     * grand total across everyone currently in view.
     */
    private function workerReport(Request $request, int $month, int $year): array
    {
        $reportFrom = $request->get('from') ?: \Carbon\Carbon::create($year, $month, 1)->startOfMonth()->toDateString();
        $reportTo = $request->get('to') ?: \Carbon\Carbon::create($year, $month, 1)->endOfMonth()->toDateString();
        $reportWorkOrderId = $request->get('work_order_id') ?: null;
        $reportEmployeeId = $request->get('employee_id') ?: null;

        $attendances = Attendance::query()
            ->whereIn('employee_id', Employee::workOrderWorkers()->pluck('id'))
            ->whereDate('date', '>=', $reportFrom)
            ->whereDate('date', '<=', $reportTo)
            ->when($reportWorkOrderId, fn ($q, $v) => $q->where('work_order_id', $v))
            ->when($reportEmployeeId, fn ($q, $v) => $q->where('employee_id', $v))
            ->with(['employee', 'workOrder'])
            ->orderBy('date')
            ->get();

        $reportByWorker = $attendances->groupBy('employee_id')
            ->map(function ($records) {
                $byWorkOrder = $records->groupBy('work_order_id')
                    ->map(fn ($woRecords) => (object) [
                        'workOrder' => $woRecords->first()->workOrder,
                        'records' => $woRecords->sortBy('date')->values(),
                        'daysPresent' => $woRecords->pluck('date')->map->format('Y-m-d')->unique()->count(),
                        'totalSalary' => (float) $woRecords->sum('salary'),
                        'totalAdvance' => (float) $woRecords->sum('advance'),
                    ])
                    ->sortBy(fn ($row) => $row->workOrder?->work_order_no ?? '')
                    ->values();

                return (object) [
                    'employee' => $records->first()->employee,
                    'byWorkOrder' => $byWorkOrder,
                    'daysPresent' => $records->pluck('date')->map->format('Y-m-d')->unique()->count(),
                    'totalSalary' => (float) $records->sum('salary'),
                    'totalAdvance' => (float) $records->sum('advance'),
                ];
            })
            ->sortBy(fn ($row) => $row->employee?->name ?? '')
            ->values();

        return [
            'reportFrom' => $reportFrom,
            'reportTo' => $reportTo,
            'reportWorkOrderId' => $reportWorkOrderId,
            'reportEmployeeId' => $reportEmployeeId,
            'reportWorkOrders' => WorkOrder::orderByDesc('created_at')->limit(200)->get(),
            'reportByWorker' => $reportByWorker,
            'reportGrandTotalSalary' => (float) $attendances->sum('salary'),
            'reportGrandTotalAdvance' => (float) $attendances->sum('advance'),
        ];
    }

    public function reportPdf(Request $request)
    {
        $report = $this->workerReport($request, (int) $request->get('month', now()->month), (int) $request->get('year', now()->year));

        $singleWorker = $report['reportEmployeeId'] ? $report['reportByWorker']->first() : null;
        $workOrderFilter = $report['reportWorkOrderId'] ? WorkOrder::find($report['reportWorkOrderId']) : null;

        $pdf = Pdf::loadView('payroll.report-pdf', $report + compact('singleWorker', 'workOrderFilter'));

        $filename = $singleWorker
            ? $singleWorker->employee->name.'-Payroll-'.$report['reportFrom'].'-to-'.$report['reportTo'].'.pdf'
            : 'WO-Workers-Payroll-'.$report['reportFrom'].'-to-'.$report['reportTo'].'.pdf';

        return $pdf->download($filename);
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
            'other_payments' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Blank optional fields arrive here as null (ConvertEmptyStringsToNull
        // + the 'nullable' rule above), but the payrolls table columns are
        // default(0) and NOT nullable - inserting $data as-is 500s the first
        // time a real user leaves one of these blank. Coerce to 0 instead.
        $allowances = (float) ($data['allowances'] ?? 0);
        $overtime = (float) ($data['overtime_amount'] ?? 0);
        $incentive = (float) ($data['incentive'] ?? 0);
        $otherPayments = (float) ($data['other_payments'] ?? 0);
        $deductions = (float) ($data['deductions'] ?? 0);
        $advanceDeducted = (float) ($data['advance_deducted'] ?? 0);

        $net = $data['basic_salary'] + $allowances + $overtime + $incentive + $otherPayments - $deductions - $advanceDeducted;

        $paidAmount = (float) (Payroll::where('employee_id', $data['employee_id'])
            ->where('month', $data['month'])->where('year', $data['year'])
            ->value('paid_amount') ?? 0);

        Payroll::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'month' => $data['month'], 'year' => $data['year']],
            [
                'basic_salary' => $data['basic_salary'],
                'allowances' => $allowances,
                'deductions' => $deductions,
                'advance_deducted' => $advanceDeducted,
                'overtime_amount' => $overtime,
                'incentive' => $incentive,
                'other_payments' => $otherPayments,
                'net_salary' => $net,
                'status' => $this->statusFor($net, $paidAmount),
                'processed_by' => $request->user()->id,
            ]
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

    public function destroyPayment(Payroll $payroll, PayrollPayment $payment): RedirectResponse
    {
        abort_unless($payment->payroll_id === $payroll->id, 404);

        $payment->delete();

        $paidAmount = (float) $payroll->payments()->sum('amount');
        $status = $this->statusFor((float) $payroll->net_salary, $paidAmount);

        $payroll->update([
            'paid_amount' => $paidAmount,
            'status' => $status,
            'paid_at' => $status === 'paid' ? ($payroll->paid_at ?? now()) : null,
        ]);

        return back()->with('success', 'Payment record removed.');
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
            'allowances' => ['nullable', 'numeric', 'min:0'],
            'overtime_amount' => ['nullable', 'numeric', 'min:0'],
            'incentive' => ['nullable', 'numeric', 'min:0'],
            'other_payments' => ['nullable', 'numeric', 'min:0'],
            'deductions' => ['nullable', 'numeric', 'min:0'],
        ]);

        $attendance = Attendance::where('employee_id', $data['employee_id'])
            ->whereMonth('date', $data['month'])
            ->whereYear('date', $data['year'])
            ->get();

        $basicSalary = (float) $attendance->sum('salary');
        $advanceDeducted = (float) $attendance->sum('advance');
        $allowances = (float) ($data['allowances'] ?? 0);
        $overtime = (float) ($data['overtime_amount'] ?? 0);
        $incentive = (float) ($data['incentive'] ?? 0);
        $otherPayments = (float) ($data['other_payments'] ?? 0);
        $deductions = (float) ($data['deductions'] ?? 0);
        $net = $basicSalary + $allowances + $overtime + $incentive + $otherPayments - $deductions - $advanceDeducted;

        $paidAmount = (float) (Payroll::where('employee_id', $data['employee_id'])
            ->where('month', $data['month'])->where('year', $data['year'])
            ->value('paid_amount') ?? 0);

        Payroll::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'month' => $data['month'], 'year' => $data['year']],
            [
                'basic_salary' => $basicSalary,
                'allowances' => $allowances,
                'deductions' => $deductions,
                'advance_deducted' => $advanceDeducted,
                'overtime_amount' => $overtime,
                'incentive' => $incentive,
                'other_payments' => $otherPayments,
                'net_salary' => $net,
                'status' => $this->statusFor($net, $paidAmount),
                'processed_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Payroll generated from attendance.');
    }

    public function destroy(Payroll $payroll): RedirectResponse
    {
        $this->authorizeAdminOnly();

        $payroll->delete();

        return back()->with('success', 'Payroll record removed.');
    }

    public function pdf(Payroll $payroll)
    {
        $payroll->load('employee', 'payments');

        $pdf = Pdf::loadView('payroll.pdf', compact('payroll'));

        $filename = $payroll->employee->name.'-'.\Carbon\Carbon::create($payroll->year, $payroll->month, 1)->format('M-Y').'.pdf';

        return $pdf->download($filename);
    }
}
