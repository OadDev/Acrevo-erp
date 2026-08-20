<?php

namespace App\Http\Controllers;

use App\Models\LeaveRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LeaveRequestController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $employee = $user->employee;
        $canReview = $user->can('attendance.manage');

        $myRequests = $employee
            ? LeaveRequest::where('employee_id', $employee->id)->latest('from_date')->get()
            : collect();

        $allRequests = $canReview
            ? LeaveRequest::with(['employee', 'reviewedBy'])
                ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
                ->latest('from_date')
                ->paginate(15)
                ->withQueryString()
            : null;

        return view('leave-requests.index', compact('myRequests', 'allRequests', 'canReview', 'employee'));
    }

    public function store(Request $request): RedirectResponse
    {
        $employee = $request->user()->employee;

        abort_unless($employee, 403, 'No worker/employee profile is linked to your account yet - ask an Admin to link one before requesting leave.');

        $data = $request->validate([
            'type' => ['required', 'in:casual,sick,earned,unpaid,other'],
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after_or_equal:from_date'],
            'reason' => ['nullable', 'string'],
        ]);

        LeaveRequest::create($data + [
            'employee_id' => $employee->id,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Leave request submitted.');
    }

    public function review(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.manage'), 403);

        $data = $request->validate([
            'status' => ['required', 'in:approved,rejected'],
            'review_remarks' => ['nullable', 'string'],
        ]);

        $leaveRequest->update($data + [
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Leave request '.$data['status'].'.');
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest): RedirectResponse
    {
        $employee = $request->user()->employee;

        abort_unless($employee && $leaveRequest->employee_id === $employee->id, 403);
        abort_unless($leaveRequest->status === 'pending', 422, 'This request has already been reviewed and can no longer be withdrawn.');

        $leaveRequest->delete();

        return back()->with('success', 'Leave request withdrawn.');
    }
}
