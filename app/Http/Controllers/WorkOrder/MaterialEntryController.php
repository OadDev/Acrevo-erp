<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\MaterialEntry;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MaterialEntryController extends Controller
{
    public function index(WorkOrder $workOrder): RedirectResponse
    {
        return redirect()->route('work-orders.show', $workOrder);
    }

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'material_name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'rate' => ['required', 'numeric', 'min:0'],
            'scope' => ['nullable', 'in:client,company'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'delivery_vehicle_details' => ['nullable', 'string', 'max:255'],
        ]);

        $workOrder->materialEntries()->create($data + [
            'amount' => $data['quantity'] * $data['rate'],
            'added_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Material inward recorded.');
    }

    public function update(Request $request, WorkOrder $workOrder, MaterialEntry $material): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($material->work_order_id === $workOrder->id, 404);

        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'material_name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'rate' => ['required', 'numeric', 'min:0'],
            'scope' => ['nullable', 'in:client,company'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'delivery_vehicle_details' => ['nullable', 'string', 'max:255'],
        ]);

        $material->update($data + [
            'amount' => $data['quantity'] * $data['rate'],
        ]);

        return back()->with('success', 'Material inward entry updated.');
    }

    public function destroy(WorkOrder $workOrder, MaterialEntry $material): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($material->work_order_id === $workOrder->id, 404);

        $material->delete();

        return back()->with('success', 'Material inward entry removed.');
    }
}
