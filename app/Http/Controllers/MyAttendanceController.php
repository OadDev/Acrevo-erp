<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MyAttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $employee = $request->user()->employee;
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        $attendances = $employee
            ? Attendance::where('employee_id', $employee->id)
                ->whereMonth('date', $month)
                ->whereYear('date', $year)
                ->with('workOrder')
                ->orderBy('date')
                ->get()
            : collect();

        return view('my-attendance.index', compact('attendances', 'employee', 'month', 'year'));
    }
}
