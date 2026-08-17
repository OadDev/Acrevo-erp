<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\MaterialUsageEntry;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MaterialUsageEntryController extends Controller
{
    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'material_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['required', 'string', 'max:30'],
        ]);

        $workOrder->materialUsageEntries()->create($data + [
            'added_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Daily material used entry recorded.');
    }

    public function update(Request $request, WorkOrder $workOrder, MaterialUsageEntry $usage): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($usage->work_order_id === $workOrder->id, 404);

        $data = $request->validate([
            'date' => ['required', 'date'],
            'material_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'unit' => ['required', 'string', 'max:30'],
        ]);

        $usage->update($data);

        return back()->with('success', 'Daily material used entry updated.');
    }

    public function destroy(WorkOrder $workOrder, MaterialUsageEntry $usage): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($usage->work_order_id === $workOrder->id, 404);

        $usage->delete();

        return back()->with('success', 'Daily material used entry removed.');
    }
}
