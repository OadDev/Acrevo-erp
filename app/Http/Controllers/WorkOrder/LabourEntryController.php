<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\LabourEntry;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LabourEntryController extends Controller
{
    public function index(WorkOrder $workOrder): RedirectResponse
    {
        return redirect()->route('work-orders.show', $workOrder);
    }

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'labour_type' => ['required', 'string', 'max:150'],
            'count' => ['required', 'integer', 'min:1'],
            'hours' => ['nullable', 'numeric', 'min:0'],
            'wage_rate' => ['required', 'numeric', 'min:0'],
            'total_time_to_finish' => ['nullable', 'string', 'max:100'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $workOrder->labourEntries()->create($data + [
            'amount' => $data['count'] * $data['wage_rate'],
            'added_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Labour entry added.');
    }

    public function update(Request $request, WorkOrder $workOrder, LabourEntry $labour): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($labour->work_order_id === $workOrder->id, 404);

        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'labour_type' => ['required', 'string', 'max:150'],
            'count' => ['required', 'integer', 'min:1'],
            'hours' => ['nullable', 'numeric', 'min:0'],
            'wage_rate' => ['required', 'numeric', 'min:0'],
            'total_time_to_finish' => ['nullable', 'string', 'max:100'],
            'remark' => ['nullable', 'string', 'max:255'],
        ]);

        $labour->update($data + [
            'amount' => $data['count'] * $data['wage_rate'],
        ]);

        return back()->with('success', 'Labour entry updated.');
    }

    public function destroy(WorkOrder $workOrder, LabourEntry $labour): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($labour->work_order_id === $workOrder->id, 404);

        $labour->delete();

        return back()->with('success', 'Labour entry removed.');
    }
}
