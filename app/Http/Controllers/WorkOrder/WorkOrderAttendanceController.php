<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkOrderAttendanceController extends Controller
{
    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,half_day,leave'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'advance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $hoursWorked = null;
        if (! empty($data['check_in']) && ! empty($data['check_out'])) {
            $hoursWorked = round(
                (strtotime($data['check_out']) - strtotime($data['check_in'])) / 3600,
                2
            );
        }

        Attendance::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'date' => $data['date']],
            [
                'work_order_id' => $workOrder->id,
                'status' => $data['status'],
                'check_in' => $data['check_in'] ?? null,
                'check_out' => $data['check_out'] ?? null,
                'hours_worked' => $hoursWorked,
                'salary' => $data['salary'] ?? null,
                'advance' => $data['advance'] ?? null,
                'marked_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Attendance recorded.');
    }
}
