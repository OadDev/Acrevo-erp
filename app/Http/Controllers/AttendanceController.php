<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $date = $request->get('date', now()->toDateString());

        // Staff only (Sales/HR/Finance/Executive Team Leader/QC Officer) -
        // Worker attendance is entered separately, via a work order's M.Book.
        $employees = Employee::where('status', 'active')
            ->staff()
            ->with(['attendances' => fn ($q) => $q->where('date', $date)])
            ->orderBy('name')
            ->get();

        // withTrashed() so a relieved staff member's historical attendance
        // can still be filtered/exported, not just currently-active staff.
        $filterEmployees = Employee::staff()->withTrashed()->orderBy('name')->get();

        $filterEmployeeId = $request->get('employee_id');
        $from = $request->get('from', now()->startOfMonth()->toDateString());
        $to = $request->get('to', now()->toDateString());

        $history = Attendance::whereIn('employee_id', $filterEmployees->pluck('id'))
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->when($filterEmployeeId, fn ($q) => $q->where('employee_id', $filterEmployeeId))
            ->with('employee')
            ->orderByDesc('date')
            ->get();

        return view('attendance.index', compact('employees', 'date', 'filterEmployees', 'filterEmployeeId', 'from', 'to', 'history'));
    }

    public function pdf(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $employee = Employee::withTrashed()->findOrFail($data['employee_id']);
        $from = $data['from'] ?? now()->startOfMonth()->toDateString();
        $to = $data['to'] ?? now()->toDateString();

        $records = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->orderBy('date')
            ->get();

        $pdf = Pdf::loadView('attendance.pdf', compact('employee', 'records', 'from', 'to'));

        $filename = $employee->name.'-Attendance-'.$from.'-to-'.$to.'.pdf';

        return $pdf->download($filename);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'attendance' => ['required', 'array'],
            'attendance.*' => ['required', 'in:present,absent,half_day,leave'],
            'work_details' => ['nullable', 'array'],
            'work_details.*' => ['nullable', 'string'],
            'salary' => ['nullable', 'array'],
            'salary.*' => ['nullable', 'numeric', 'min:0'],
            'advance' => ['nullable', 'array'],
            'advance.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        // Only ever mark attendance for staff employees here, even if an
        // employee_id from outside that list somehow made it into the
        // request - Worker attendance must stay exclusive to the M.Book.
        $staffEmployeeIds = Employee::staff()->whereIn('id', array_keys($data['attendance']))->pluck('id');

        foreach ($data['attendance'] as $employeeId => $status) {
            if (! $staffEmployeeIds->contains((int) $employeeId)) {
                continue;
            }

            Attendance::updateOrCreate(
                ['employee_id' => $employeeId, 'date' => $data['date']],
                [
                    'status' => $status,
                    'work_details' => $data['work_details'][$employeeId] ?? null,
                    'salary' => $data['salary'][$employeeId] ?? null,
                    'advance' => $data['advance'][$employeeId] ?? null,
                    'marked_by' => $request->user()->id,
                ]
            );
        }

        return back()->with('success', 'Attendance saved for '.$data['date'].'.');
    }
}
