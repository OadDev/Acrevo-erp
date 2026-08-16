<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
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
}
