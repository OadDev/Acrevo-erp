<?php

namespace App\Http\Controllers;

use App\Models\Payroll;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyPayrollController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $request->user()->employee;

        $payrolls = $employee
            ? Payroll::where('employee_id', $employee->id)->with('payments')->orderByDesc('year')->orderByDesc('month')->get()
            : collect();

        return view('my-payroll.index', compact('payrolls', 'employee'));
    }

    public function pdf(Request $request, Payroll $payroll)
    {
        abort_unless($payroll->employee_id === $request->user()->employee?->id, 403);

        $payroll->load('employee', 'payments');

        $pdf = Pdf::loadView('payroll.pdf', compact('payroll'));

        $filename = $payroll->employee->name.'-'.\Carbon\Carbon::create($payroll->year, $payroll->month, 1)->format('M-Y').'.pdf';

        return $pdf->download($filename);
    }
}
