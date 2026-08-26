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
            'break_minutes' => ['nullable', 'integer', 'min:0'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'advance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $hoursWorked = null;
        if (! empty($data['check_in']) && ! empty($data['check_out'])) {
            $hoursWorked = round(
                (strtotime($data['check_out']) - strtotime($data['check_in'])) / 3600
                - (($data['break_minutes'] ?? 0) / 60),
                2
            );
            $hoursWorked = max($hoursWorked, 0);
        }

        Attendance::updateOrCreate(
            ['employee_id' => $data['employee_id'], 'date' => $data['date']],
            [
                'work_order_id' => $workOrder->id,
                'status' => $data['status'],
                'check_in' => $data['check_in'] ?? null,
                'check_out' => $data['check_out'] ?? null,
                'break_minutes' => $data['break_minutes'] ?? null,
                'hours_worked' => $hoursWorked,
                'salary' => $data['salary'] ?? null,
                'advance' => $data['advance'] ?? null,
                'marked_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Attendance recorded.');
    }

    public function update(Request $request, WorkOrder $workOrder, Attendance $attendance): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($attendance->work_order_id === $workOrder->id, 404);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'status' => ['required', 'in:present,absent,half_day,leave'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i', 'after:check_in'],
            'break_minutes' => ['nullable', 'integer', 'min:0'],
            'salary' => ['nullable', 'numeric', 'min:0'],
            'advance' => ['nullable', 'numeric', 'min:0'],
        ]);

        $hoursWorked = null;
        if (! empty($data['check_in']) && ! empty($data['check_out'])) {
            $hoursWorked = round(
                (strtotime($data['check_out']) - strtotime($data['check_in'])) / 3600
                - (($data['break_minutes'] ?? 0) / 60),
                2
            );
            $hoursWorked = max($hoursWorked, 0);
        }

        $attendance->update([
            'date' => $data['date'],
            'status' => $data['status'],
            'check_in' => $data['check_in'] ?? null,
            'check_out' => $data['check_out'] ?? null,
            'break_minutes' => $data['break_minutes'] ?? null,
            'hours_worked' => $hoursWorked,
            'salary' => $data['salary'] ?? null,
            'advance' => $data['advance'] ?? null,
        ]);

        return back()->with('success', 'Attendance updated.');
    }

    public function destroy(WorkOrder $workOrder, Attendance $attendance): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($attendance->work_order_id === $workOrder->id, 404);

        $attendance->delete();

        return back()->with('success', 'Attendance entry removed.');
    }
}
