<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksSiteTeamLeadership;
use App\Models\Asset;
use App\Models\AssetMovement;
use App\Models\AssetStock;
use App\Models\WorkOrder;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetMovementController extends Controller
{
    use ChecksSiteTeamLeadership;

    public function index(Request $request): View
    {
        $movements = AssetMovement::query()
            // withTrashed() - a movement stays in the history even after the
            // asset it refers to has been removed, so the relation must
            // still resolve or route('assets.show', ...) below throws.
            ->with(['asset' => fn ($q) => $q->withTrashed(), 'fromWorkOrder', 'toWorkOrder', 'createdBy'])
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->orWhereHas('asset', fn ($q3) => $q3->where('asset_code', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhere('serial_number', 'like', "%{$search}%"))
                ->orWhereHas('fromWorkOrder', fn ($q3) => $q3->where('work_order_no', 'like', "%{$search}%"))
                ->orWhereHas('toWorkOrder', fn ($q3) => $q3->where('work_order_no', 'like', "%{$search}%"))))
            ->when($request->get('from_location'), fn ($q, $v) => $q->where('from_location', $v))
            ->when($request->get('to_location'), fn ($q, $v) => $q->where('to_location', $v))
            ->when($request->get('type'), fn ($q, $v) => $q->where('type', $v))
            ->when($request->get('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->get('work_order_id'), fn ($q, $v) => $q->where(fn ($q2) => $q2
                ->where('from_work_order_id', $v)->orWhere('to_work_order_id', $v)))
            ->when($request->get('created_by'), fn ($q, $v) => $q->where('created_by', $v))
            ->when($request->get('from'), fn ($q, $v) => $q->whereDate('moved_at', '>=', $v))
            ->when($request->get('to'), fn ($q, $v) => $q->whereDate('moved_at', '<=', $v))
            ->orderByDesc('moved_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $workOrders = WorkOrder::orderByDesc('created_at')->limit(200)->get();

        return view('assets.movements.index', compact('movements', 'workOrders'));
    }

    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', AssetMovement::TYPES)],
            'from_location' => ['required', 'in:'.implode(',', AssetMovement::LOCATIONS)],
            'from_work_order_id' => ['nullable', 'exists:work_orders,id'],
            'to_location' => ['required', 'in:'.implode(',', AssetMovement::LOCATIONS)],
            'to_work_order_id' => ['required_if:to_location,work_order', 'nullable', 'exists:work_orders,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'moved_at' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        // Admin and Management have company-wide reach (they can dispatch
        // an asset from anywhere, including the company store); anyone else
        // holding movements.create - an Executive Team Leader, by default -
        // is scoped to stock currently at a site they actually lead.
        if (! $user->hasRole('Admin') && ! $user->hasRole('Management')) {
            abort_unless($this->isTeamLeaderOfWorkOrder($data['from_work_order_id'] ?? null, $user), 403, 'You can only move assets currently at a site you lead.');
        }

        $available = AssetStock::availableAt($asset, $data['from_location'], $data['from_work_order_id'] ?? null);
        if ($data['quantity'] > $available) {
            return back()->withErrors(['quantity' => "Only {$available} available at that location."])->withInput();
        }

        $movement = AssetMovement::create($data + [
            'asset_id' => $asset->id,
            'status' => 'pending',
            'created_by' => $user->id,
        ]);

        // Reserved the moment it's recorded - leaves "available" at the
        // source right away and shows as "in transit" at the destination
        // until the receiving side confirms it.
        AssetStock::adjust($asset, $movement->from_location, $movement->from_work_order_id, 'available', -$movement->quantity);
        AssetStock::adjust($asset, $movement->to_location, $movement->to_work_order_id, 'in_transit', $movement->quantity);

        return back()->with('success', 'Movement recorded and awaiting confirmation at the destination.');
    }

    public function edit(AssetMovement $movement): View
    {
        abort_unless($movement->status === 'pending', 422, 'This movement has already been confirmed or cancelled and can no longer be edited.');

        $movement->load('asset');
        $workOrders = WorkOrder::orderByDesc('created_at')->limit(200)->get();

        return view('assets.movements.edit', compact('movement', 'workOrders'));
    }

    public function update(Request $request, AssetMovement $movement): RedirectResponse
    {
        abort_unless($movement->status === 'pending', 422, 'This movement has already been confirmed or cancelled and can no longer be edited.');

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', AssetMovement::TYPES)],
            'to_location' => ['required', 'in:'.implode(',', AssetMovement::LOCATIONS)],
            'to_work_order_id' => ['required_if:to_location,work_order', 'nullable', 'exists:work_orders,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'moved_at' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $asset = $movement->asset;

        // Undo this movement's existing reservation, then check the new
        // quantity against what that frees up at the source - simplest way
        // to keep the ledger correct whatever combination of type/
        // to_location/quantity actually changed.
        AssetStock::adjust($asset, $movement->to_location, $movement->to_work_order_id, 'in_transit', -$movement->quantity);
        AssetStock::adjust($asset, $movement->from_location, $movement->from_work_order_id, 'available', $movement->quantity);

        $available = AssetStock::availableAt($asset, $movement->from_location, $movement->from_work_order_id);
        if ($data['quantity'] > $available) {
            // Redo the original reservation before bailing, so a rejected
            // edit doesn't leave the ledger mid-change.
            AssetStock::adjust($asset, $movement->from_location, $movement->from_work_order_id, 'available', -$movement->quantity);
            AssetStock::adjust($asset, $movement->to_location, $movement->to_work_order_id, 'in_transit', $movement->quantity);

            return back()->withErrors(['quantity' => "Only {$available} available at that location."])->withInput();
        }

        AssetStock::adjust($asset, $movement->from_location, $movement->from_work_order_id, 'available', -$data['quantity']);
        AssetStock::adjust($asset, $data['to_location'], $data['to_work_order_id'] ?? null, 'in_transit', $data['quantity']);

        $movement->update($data);

        return redirect()->route('assets.show', $movement->asset_id)->with('success', 'Movement updated.');
    }

    /**
     * Confirms a pending movement: moves its reserved quantity from
     * "in transit" to "available" at the destination bucket, and closes
     * out the movement record. Anyone with movements.approve can confirm
     * any movement except an Executive Team Leader, who is scoped to
     * confirming receipt only at a site they lead (mirrors how
     * assets.update_status is scoped in AssetController).
     */
    public function confirm(Request $request, AssetMovement $movement): RedirectResponse
    {
        abort_unless($movement->status === 'pending', 422, 'This movement has already been reviewed.');

        $user = $request->user();

        if (! $user->hasRole('Admin')) {
            $receivingAtOwnSite = $movement->to_location === 'work_order'
                && $this->isTeamLeaderOfWorkOrder($movement->to_work_order_id, $user);

            abort_unless($receivingAtOwnSite, 403, 'You can only confirm movements arriving at a site you lead.');
        }

        $asset = $movement->asset;

        AssetStock::adjust($asset, $movement->to_location, $movement->to_work_order_id, 'in_transit', -$movement->quantity);
        AssetStock::adjust($asset, $movement->to_location, $movement->to_work_order_id, 'available', $movement->quantity);

        // Keeps the legacy single current_location/current_work_order_id in
        // sync for the common case - a one-off tool moving as a whole,
        // where the source now has nothing available left. A partial
        // movement (bulk stock split across locations) leaves them as-is;
        // the Stock by Location breakdown is the accurate view once an
        // asset's quantity is split.
        if (AssetStock::availableAt($asset, $movement->from_location, $movement->from_work_order_id) === 0) {
            $asset->update([
                'current_location' => $movement->to_location,
                'current_work_order_id' => $movement->to_work_order_id,
            ]);
        }

        $movement->update([
            'status' => 'confirmed',
            'confirmed_by' => $user->id,
            'confirmed_at' => now(),
        ]);

        return back()->with('success', 'Movement confirmed - the asset\'s current location has been updated.');
    }

    public function cancel(AssetMovement $movement): RedirectResponse
    {
        abort_unless($movement->status === 'pending', 422, 'This movement has already been reviewed.');

        $asset = $movement->asset;
        AssetStock::adjust($asset, $movement->to_location, $movement->to_work_order_id, 'in_transit', -$movement->quantity);
        AssetStock::adjust($asset, $movement->from_location, $movement->from_work_order_id, 'available', $movement->quantity);

        $movement->update(['status' => 'cancelled']);

        return back()->with('success', 'Movement cancelled. The asset\'s location is unchanged.');
    }

    public function destroy(AssetMovement $movement): RedirectResponse
    {
        if ($movement->status === 'pending') {
            // Reverse the outstanding reservation first - otherwise removing
            // this history entry would strand its quantity "in transit"
            // forever, with no movement record left to release it.
            // withTrashed() - the asset may have been removed since.
            $asset = Asset::withTrashed()->find($movement->asset_id);
            if ($asset) {
                AssetStock::adjust($asset, $movement->to_location, $movement->to_work_order_id, 'in_transit', -$movement->quantity);
                AssetStock::adjust($asset, $movement->from_location, $movement->from_work_order_id, 'available', $movement->quantity);
            }
        }

        $movement->delete();

        return back()->with('success', 'Movement removed.');
    }

    public function pdf(Asset $asset)
    {
        $asset->load(['movements.fromWorkOrder', 'movements.toWorkOrder', 'movements.createdBy', 'movements.confirmedBy']);

        $pdf = Pdf::loadView('assets.movements.pdf', compact('asset'));

        return $pdf->download("{$asset->asset_code}-movement-history.pdf");
    }
}
