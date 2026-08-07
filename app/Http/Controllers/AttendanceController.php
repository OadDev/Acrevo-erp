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

        $employees = Employee::where('status', 'active')
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
        ]);

        foreach ($data['attendance'] as $employeeId => $status) {
            Attendance::updateOrCreate(
                ['employee_id' => $employeeId, 'date' => $data['date']],
                ['status' => $status, 'marked_by' => $request->user()->id]
            );
        }

        return back()->with('success', 'Attendance saved for '.$data['date'].'.');
    }
}
