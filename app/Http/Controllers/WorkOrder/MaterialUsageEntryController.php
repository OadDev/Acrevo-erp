<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
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
}
