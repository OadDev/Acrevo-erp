<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
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
}
