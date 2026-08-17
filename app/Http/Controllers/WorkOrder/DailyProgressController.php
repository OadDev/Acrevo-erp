<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\DailyProgressReport;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyProgressController extends Controller
{
    public function index(WorkOrder $workOrder): RedirectResponse
    {
        return redirect()->route('work-orders.show', $workOrder);
    }

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'executive_team_id' => ['required', 'exists:executive_teams,id'],
            'completed_work' => ['required', 'string'],
            'pending_work' => ['nullable', 'string'],
            'problems' => ['nullable', 'string'],
            'materials_required' => ['nullable', 'string'],
        ]);

        $workOrder->dailyProgressReports()->create($data + [
            'date' => now()->toDateString(),
            'submitted_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Progress report submitted.');
    }

    public function update(Request $request, WorkOrder $workOrder, DailyProgressReport $report): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($report->work_order_id === $workOrder->id, 404);

        $data = $request->validate([
            'executive_team_id' => ['required', 'exists:executive_teams,id'],
            'completed_work' => ['required', 'string'],
            'pending_work' => ['nullable', 'string'],
            'problems' => ['nullable', 'string'],
            'materials_required' => ['nullable', 'string'],
        ]);

        $report->update($data);

        return back()->with('success', 'Progress report updated.');
    }

    public function destroy(WorkOrder $workOrder, DailyProgressReport $report): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($report->work_order_id === $workOrder->id, 404);

        $report->delete();

        return back()->with('success', 'Progress report removed.');
    }
}
