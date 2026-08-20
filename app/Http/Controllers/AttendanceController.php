<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
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

        return view('attendance.index', compact('employees', 'date'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'attendance' => ['required', 'array'],
            'attendance.*' => ['required', 'in:present,absent,half_day,leave'],
            'work_details' => ['nullable', 'array'],
            'work_details.*' => ['nullable', 'string'],
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
                    'marked_by' => $request->user()->id,
                ]
            );
        }

        return back()->with('success', 'Attendance saved for '.$data['date'].'.');
    }
}
