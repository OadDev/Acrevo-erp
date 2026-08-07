<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
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
            'material_name' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:30'],
            'quantity' => ['required', 'numeric', 'min:0.01'],
            'rate' => ['required', 'numeric', 'min:0'],
            'vendor' => ['nullable', 'string', 'max:255'],
        ]);

        $workOrder->materialEntries()->create($data + [
            'amount' => $data['quantity'] * $data['rate'],
            'entry_date' => now()->toDateString(),
            'added_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Material entry added.');
    }
}
