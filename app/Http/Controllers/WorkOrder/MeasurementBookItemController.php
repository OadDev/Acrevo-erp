<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\MeasurementBook;
use App\Models\MeasurementBookItem;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MeasurementBookItemController extends Controller
{
    public function store(Request $request, WorkOrder $workOrder, MeasurementBook $measurementBook): RedirectResponse
    {
        abort_unless($measurementBook->work_order_id === $workOrder->id, 404);

        $data = $request->validate([
            'item_description' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'breadth' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $rate = $data['rate'] ?? 0;

        $measurementBook->items()->create($data + [
            'rate' => $rate,
            'amount' => $data['quantity'] * $rate,
        ]);

        return back()->with('success', 'Work done entry added.');
    }

    public function update(Request $request, WorkOrder $workOrder, MeasurementBook $measurementBook, MeasurementBookItem $item): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($measurementBook->work_order_id === $workOrder->id, 404);
        abort_unless($item->measurement_book_id === $measurementBook->id, 404);

        $data = $request->validate([
            'item_description' => ['required', 'string', 'max:255'],
            'unit' => ['required', 'string', 'max:50'],
            'length' => ['nullable', 'numeric', 'min:0'],
            'breadth' => ['nullable', 'numeric', 'min:0'],
            'height' => ['nullable', 'numeric', 'min:0'],
            'quantity' => ['required', 'numeric', 'min:0'],
            'rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $rate = $data['rate'] ?? 0;

        $item->update($data + [
            'rate' => $rate,
            'amount' => $data['quantity'] * $rate,
        ]);

        return back()->with('success', 'Work done entry updated.');
    }

    public function destroy(WorkOrder $workOrder, MeasurementBook $measurementBook, MeasurementBookItem $item): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($measurementBook->work_order_id === $workOrder->id, 404);
        abort_unless($item->measurement_book_id === $measurementBook->id, 404);

        $item->delete();

        return back()->with('success', 'Work done entry removed.');
    }
}
