<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderAsset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WorkOrderAssetController extends Controller
{
    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $this->validateEntry($request);

        $workOrder->assets()->create($data + ['created_by' => $request->user()->id]);

        return back()->with('success', 'Asset entry recorded.');
    }

    public function update(Request $request, WorkOrder $workOrder, WorkOrderAsset $workOrderAsset): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($workOrderAsset->work_order_id === $workOrder->id, 404);

        $workOrderAsset->update($this->validateEntry($request));

        return back()->with('success', 'Asset entry updated.');
    }

    public function destroy(WorkOrder $workOrder, WorkOrderAsset $workOrderAsset): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($workOrderAsset->work_order_id === $workOrder->id, 404);

        $workOrderAsset->delete();

        return back()->with('success', 'Asset entry removed.');
    }

    private function validateEntry(Request $request): array
    {
        $data = $request->validate([
            'asset_name' => ['required', 'string', 'max:150'],
            'allocated_quantity' => ['required', 'integer', 'min:1'],
            'damaged_quantity' => ['nullable', 'integer', 'min:0'],
            'missing_quantity' => ['nullable', 'integer', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ]);

        $data['damaged_quantity'] = $data['damaged_quantity'] ?? 0;
        $data['missing_quantity'] = $data['missing_quantity'] ?? 0;

        abort_if($data['damaged_quantity'] + $data['missing_quantity'] > $data['allocated_quantity'], 422, 'Damaged + Missing quantity cannot exceed the total allocated quantity.');

        return $data;
    }
}
