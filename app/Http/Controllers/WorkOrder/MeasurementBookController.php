<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\MeasurementBook;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MeasurementBookController extends Controller
{
    public function index(WorkOrder $workOrder): RedirectResponse
    {
        return redirect()->route('work-orders.show', $workOrder);
    }

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'description' => ['required', 'string'],
            'date' => ['required', 'date'],
        ]);

        $workOrder->measurementBooks()->create($data + [
            'type' => 'actual',
            'recorded_by' => $request->user()->id,
            'status' => 'draft',
        ]);

        return back()->with('success', 'Measurement book entry created.');
    }

    public function update(Request $request, WorkOrder $workOrder, MeasurementBook $measurementBook): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($measurementBook->work_order_id === $workOrder->id, 404);

        $data = $request->validate([
            'description' => ['required', 'string'],
            'date' => ['required', 'date'],
        ]);

        $measurementBook->update($data);

        return back()->with('success', 'Measurement book entry updated.');
    }

    public function destroy(WorkOrder $workOrder, MeasurementBook $measurementBook): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($measurementBook->work_order_id === $workOrder->id, 404);

        $measurementBook->items()->delete();
        $measurementBook->delete();

        return back()->with('success', 'Measurement book entry removed.');
    }
}
