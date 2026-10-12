<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\MaterialEntry;
use App\Models\WorkOrder;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class MaterialEntryController extends Controller
{
    public function index(WorkOrder $workOrder): RedirectResponse
    {
        return redirect()->route('work-orders.show', $workOrder);
    }

    /**
     * Same filter fields as the Material Inward tab's own filter bar
     * (material/vendor/scope/entry-date range), queried fresh here rather
     * than reusing the already-loaded $workOrder->materialEntries
     * collection, so the PDF reflects the filter even for a work order
     * with more entries than are eager-loaded on the show page.
     */
    private function filteredEntries(Request $request, WorkOrder $workOrder)
    {
        return $workOrder->materialEntries()
            ->when($request->get('material'), fn ($q, $v) => $q->where('material_name', 'like', "%{$v}%"))
            ->when($request->get('vendor'), fn ($q, $v) => $q->where('vendor', 'like', "%{$v}%"))
            ->when($request->get('scope'), fn ($q, $v) => $q->where('scope', $v))
            ->when($request->get('from'), fn ($q, $v) => $q->whereDate('entry_date', '>=', $v))
            ->when($request->get('to'), fn ($q, $v) => $q->whereDate('entry_date', '<=', $v))
            ->get();
    }

    public function pdf(Request $request, WorkOrder $workOrder)
    {
        $entries = $this->filteredEntries($request, $workOrder);

        $pdf = Pdf::loadView('work-orders.materials-pdf', [
            'workOrder' => $workOrder,
            'entries' => $entries,
            'material' => $request->get('material'),
            'vendor' => $request->get('vendor'),
            'scope' => $request->get('scope'),
            'from' => $request->get('from'),
            'to' => $request->get('to'),
        ]);

        return $pdf->download("{$workOrder->work_order_no}-Material-Inward.pdf");
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
            'remarks' => ['nullable', 'string'],
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
            'remarks' => ['nullable', 'string'],
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
