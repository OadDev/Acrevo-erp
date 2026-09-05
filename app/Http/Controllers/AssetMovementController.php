<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksSiteTeamLeadership;
use App\Models\Asset;
use App\Models\AssetMovement;
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

        // Admin and Management have company-wide reach (they can dispatch
        // an asset from anywhere, including the company store); anyone else
        // holding movements.create - an Executive Team Leader, by default -
        // is scoped to assets currently at a site they actually lead.
        if (! $user->hasRole('Admin') && ! $user->hasRole('Management')) {
            abort_unless($this->isTeamLeaderOfWorkOrder($asset->current_work_order_id, $user), 403, 'You can only move assets currently at a site you lead.');
        }

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', AssetMovement::TYPES)],
            'to_location' => ['required', 'in:'.implode(',', AssetMovement::LOCATIONS)],
            'to_work_order_id' => ['required_if:to_location,work_order', 'nullable', 'exists:work_orders,id'],
            'moved_at' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        AssetMovement::create($data + [
            'asset_id' => $asset->id,
            'from_location' => $asset->current_location,
            'from_work_order_id' => $asset->current_work_order_id,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

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
            'moved_at' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $movement->update($data);

        return redirect()->route('assets.show', $movement->asset_id)->with('success', 'Movement updated.');
    }

    /**
     * Confirms a pending movement: updates the asset's current location and
     * closes out the movement record. Anyone with movements.approve can
     * confirm any movement except an Executive Team Leader, who is scoped
     * to confirming receipt only at a site they lead (mirrors how
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

        $movement->asset->update([
            'current_location' => $movement->to_location,
            'current_work_order_id' => $movement->to_work_order_id,
        ]);

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

        $movement->update(['status' => 'cancelled']);

        return back()->with('success', 'Movement cancelled. The asset\'s location is unchanged.');
    }

    public function pdf(Asset $asset)
    {
        $asset->load(['movements.fromWorkOrder', 'movements.toWorkOrder', 'movements.createdBy', 'movements.confirmedBy']);

        $pdf = Pdf::loadView('assets.movements.pdf', compact('asset'));

        return $pdf->download("{$asset->asset_code}-movement-history.pdf");
    }
}
