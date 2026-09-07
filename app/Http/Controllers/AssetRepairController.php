<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksSiteTeamLeadership;
use App\Http\Controllers\Concerns\LogsAssetStatusChanges;
use App\Models\Asset;
use App\Models\AssetRepair;
use App\Models\AssetStock;
use App\Models\WorkOrder;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class AssetRepairController extends Controller
{
    use ChecksSiteTeamLeadership, LogsAssetStatusChanges;

    public function index(Request $request): View
    {
        $repairs = AssetRepair::query()
            ->with(['asset' => fn ($q) => $q->withTrashed(), 'workOrder', 'createdBy'])
            ->when($request->get('q'), fn ($q, $search) => $q->whereHas('asset', fn ($q2) => $q2
                ->where('asset_code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%")))
            ->when($request->get('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->get('repair_type'), fn ($q, $v) => $q->where('repair_type', $v))
            ->when($request->get('technician_vendor'), fn ($q, $v) => $q->where('technician_vendor', 'like', "%{$v}%"))
            ->when($request->get('warranty') !== null && $request->get('warranty') !== '', fn ($q) => $q->where('is_warranty_repair', $request->boolean('warranty')))
            ->when($request->get('work_order_id'), fn ($q, $v) => $q->where('work_order_id', $v))
            ->when($request->get('from'), fn ($q, $v) => $q->whereDate('reported_date', '>=', $v))
            ->when($request->get('to'), fn ($q, $v) => $q->whereDate('reported_date', '<=', $v))
            ->when($request->get('cost_min'), fn ($q, $v) => $q->where('cost', '>=', $v))
            ->when($request->get('cost_max'), fn ($q, $v) => $q->where('cost', '<=', $v))
            ->orderByDesc('reported_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $workOrders = WorkOrder::orderByDesc('created_at')->limit(200)->get();

        return view('assets.repairs.index', compact('repairs', 'workOrders'));
    }

    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'repair_type' => ['required', 'in:'.implode(',', AssetRepair::TYPES)],
            'location' => ['required', 'in:'.implode(',', Asset::LOCATIONS)],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'issue_description' => ['required', 'string'],
            'technician_vendor' => ['nullable', 'string', 'max:255'],
            'is_warranty_repair' => ['nullable', 'boolean'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'reported_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:51200', 'mimes:jpg,jpeg,png,pdf,doc,docx,mp4,mov,avi'],
        ]);

        // Admin and Management have company-wide reach; anyone else holding
        // repairs.create - an Executive Team Leader, by default - can only
        // report a repair for stock currently at a site they lead.
        if (! $user->hasRole('Admin') && ! $user->hasRole('Management')) {
            abort_unless($this->isTeamLeaderOfWorkOrder($data['work_order_id'] ?? null, $user), 403, 'You can only report repairs for assets at a site you lead.');
        }

        $available = AssetStock::availableAt($asset, $data['location'], $data['work_order_id'] ?? null);
        if ($data['quantity'] > $available) {
            return back()->withErrors(['quantity' => "Only {$available} available at that location."])->withInput();
        }

        $repair = AssetRepair::create(collect($data)->except('attachments')->all() + [
            'asset_id' => $asset->id,
            'is_warranty_repair' => $request->boolean('is_warranty_repair'),
            'status' => 'reported',
            'asset_status_before' => $asset->status,
            'created_by' => $user->id,
        ]);

        AssetStock::adjust($asset, $data['location'], $data['work_order_id'] ?? null, 'available', -$data['quantity']);
        AssetStock::adjust($asset, $data['location'], $data['work_order_id'] ?? null, 'under_repair', $data['quantity']);

        // Legacy whole-asset status flag only flips when this repair covers
        // everything that was available there, mirroring how a Movement's
        // confirm() keeps current_location in sync - so a partial repair on
        // bulk stock doesn't misreport the rest of the asset as under repair.
        if ($data['quantity'] === $available) {
            $this->transitionAssetStatus($asset, 'under_repair', $user, "Repair #{$repair->id} reported.");
        }

        try {
            foreach ($request->file('attachments', []) as $file) {
                $repair->addMedia($file)->toMediaCollection('attachments');
            }
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['attachments' => 'One of those files is too large (max 50MB).']);
        }

        return back()->with('success', 'Repair entry recorded.');
    }

    public function edit(AssetRepair $repair): View
    {
        $repair->load('asset');

        return view('assets.repairs.edit', compact('repair'));
    }

    /**
     * quantity/location/work_order_id are deliberately not editable here -
     * they already moved quantity from Available to Under Repair when this
     * entry was reported, and changing them after the fact without
     * re-deriving that ledger effect would desync it. Only the descriptive
     * fields can be corrected.
     */
    public function update(Request $request, AssetRepair $repair): RedirectResponse
    {
        $data = $request->validate([
            'repair_type' => ['required', 'in:'.implode(',', AssetRepair::TYPES)],
            'issue_description' => ['required', 'string'],
            'technician_vendor' => ['nullable', 'string', 'max:255'],
            'is_warranty_repair' => ['nullable', 'boolean'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'reported_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $repair->update($data + ['is_warranty_repair' => $request->boolean('is_warranty_repair')]);

        return redirect()->route('assets.show', $repair->asset_id)->with('success', 'Repair entry updated.');
    }

    /**
     * Moves a repair through reported -> in_progress -> completed, or
     * cancels it - each transition keeps the asset's own operational
     * status (Asset::STATUSES) in sync and logs it, same as a manual
     * status update would.
     */
    public function updateStatus(Request $request, AssetRepair $repair): RedirectResponse
    {
        $user = $request->user();
        $asset = $repair->asset;

        if (! $user->hasRole('Admin')) {
            abort_unless($this->isTeamLeaderOfWorkOrder($repair->work_order_id, $user), 403, 'You can only update repairs for assets at a site you lead.');
        }

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', AssetRepair::STATUSES)],
        ]);

        $previousStatus = $repair->status;

        $repair->update([
            'status' => $data['status'],
            'completed_date' => $data['status'] === 'completed' ? now()->toDateString() : $repair->completed_date,
        ]);

        // 'in_progress' leaves the quantity right where it is (still under
        // repair either way). 'completed'/'cancelled' both return it to
        // Available - only once, whichever terminal status it lands on.
        if (in_array($data['status'], ['completed', 'cancelled'], true) && ! in_array($previousStatus, ['completed', 'cancelled'], true)) {
            AssetStock::adjust($asset, $repair->location, $repair->work_order_id, 'under_repair', -$repair->quantity);
            AssetStock::adjust($asset, $repair->location, $repair->work_order_id, 'available', $repair->quantity);
        }

        match ($data['status']) {
            'in_progress' => $this->transitionAssetStatus($asset, 'under_repair', $user, "Repair #{$repair->id} in progress."),
            'completed' => $this->transitionAssetStatus($asset, 'repaired', $user, "Repair #{$repair->id} completed."),
            'cancelled' => $this->transitionAssetStatus($asset, $repair->asset_status_before, $user, "Repair #{$repair->id} cancelled."),
            default => null,
        };

        return back()->with('success', "Repair marked \"{$data['status']}\".");
    }

    public function destroy(AssetRepair $repair): RedirectResponse
    {
        if (! in_array($repair->status, ['completed', 'cancelled'], true)) {
            // Reverse the outstanding under-repair quantity first -
            // otherwise removing this history entry would strand it there
            // forever, with no repair record left to release it.
            // withTrashed() - the asset may have been removed since.
            $asset = Asset::withTrashed()->find($repair->asset_id);
            if ($asset) {
                AssetStock::adjust($asset, $repair->location, $repair->work_order_id, 'under_repair', -$repair->quantity);
                AssetStock::adjust($asset, $repair->location, $repair->work_order_id, 'available', $repair->quantity);
            }
        }

        $repair->delete();

        return back()->with('success', 'Repair entry removed.');
    }

    public function pdf(Asset $asset)
    {
        $asset->load(['repairs.workOrder', 'repairs.createdBy']);

        $pdf = Pdf::loadView('assets.repairs.pdf', compact('asset'));

        return $pdf->download("{$asset->asset_code}-repair-history.pdf");
    }
}
