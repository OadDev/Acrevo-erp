<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderSummary;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkOrderSummaryController extends Controller
{
    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorizeSummaryEditors();

        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'status' => ['required', 'in:done,not_done'],
            'responsibility' => ['required', 'in:client,company'],
            'work_detail' => ['nullable', 'string'],
            'client_bear_days' => ['nullable', 'integer', 'min:0'],
            'remaining_construction_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $workOrder->summaries()->create($data + [
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Summary entry recorded.');
    }

    public function update(Request $request, WorkOrder $workOrder, WorkOrderSummary $summary): RedirectResponse
    {
        $this->authorizeSummaryEditors();

        abort_unless($summary->work_order_id === $workOrder->id, 404);

        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'status' => ['required', 'in:done,not_done'],
            'responsibility' => ['required', 'in:client,company'],
            'work_detail' => ['nullable', 'string'],
            'client_bear_days' => ['nullable', 'integer', 'min:0'],
            'remaining_construction_days' => ['nullable', 'integer', 'min:0'],
        ]);

        $summary->update($data);

        return back()->with('success', 'Summary entry updated.');
    }

    public function destroy(WorkOrder $workOrder, WorkOrderSummary $summary): RedirectResponse
    {
        $this->authorizeSummaryEditors();

        abort_unless($summary->work_order_id === $workOrder->id, 404);

        $summary->delete();

        return back()->with('success', 'Summary entry removed.');
    }
}
