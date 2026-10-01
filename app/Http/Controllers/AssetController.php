<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksSiteTeamLeadership;
use App\Models\Asset;
use App\Models\AssetChangeRequest;
use App\Models\AssetMovement;
use App\Models\AssetStatusLog;
use App\Models\AssetStock;
use App\Models\WorkOrder;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AssetController extends Controller
{
    use ChecksSiteTeamLeadership;

    private const ATTACHMENT_RULES = ['file', 'max:51200', 'mimes:jpg,jpeg,png,pdf,doc,docx,mp4,mov,avi'];

    private const MASTER_DETAIL_FIELDS = [
        'name', 'category', 'brand', 'model', 'serial_number',
        'purchase_date', 'purchase_cost', 'supplier', 'invoice_number',
        'warranty_start', 'warranty_end', 'warranty_provider', 'warranty_card_details', 'remarks',
    ];

    public function index(Request $request): View
    {
        $showRemoved = $request->boolean('removed') && $request->user()->can('assets.restore');

        $filterWorkOrderId = $request->get('work_order_id');

        $assets = Asset::query()
            ->when($showRemoved, fn ($q) => $q->onlyTrashed())
            ->with('currentWorkOrder.site')
            // Once an asset's quantity is split across locations, the
            // legacy current_work_order_id only ever reflects wherever the
            // last movement happened to send the whole remaining balance
            // (see AssetMovementController::confirm()) - it can easily
            // point somewhere the asset no longer has any stock at all,
            // while staying silent about every work order it's actually
            // sitting at. The AssetStock ledger is the source of truth for
            // "is any of this asset currently at this work order", so the
            // filter has to go through it instead.
            ->when($filterWorkOrderId, fn ($q, $v) => $q->whereHas('stocks', fn ($q2) => $q2
                ->where('work_order_id', $v)
                ->where('quantity', '>', 0)))
            ->when($filterWorkOrderId, fn ($q, $v) => $q->with(['stocks' => fn ($q2) => $q2
                ->where('work_order_id', $v)
                ->where('quantity', '>', 0)]))
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('asset_code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")
                ->orWhere('model', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")
                ->orWhere('supplier', 'like', "%{$search}%")
                ->orWhereHas('currentWorkOrder', fn ($q3) => $q3->where('work_order_no', 'like', "%{$search}%"))))
            ->when($request->get('category'), fn ($q, $v) => $q->where('category', $v))
            ->when($request->get('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->get('condition'), fn ($q, $v) => $q->where('condition', $v))
            ->when($request->get('brand'), fn ($q, $v) => $q->where('brand', $v))
            ->when($request->get('current_location'), fn ($q, $v) => $q->where('current_location', $v))
            ->when($request->get('warranty_status'), function ($q, $v) {
                return match ($v) {
                    'active' => $q->whereNotNull('warranty_end')->where('warranty_end', '>', now()->addDays(30)),
                    'expiring_soon' => $q->whereNotNull('warranty_end')->whereBetween('warranty_end', [now(), now()->addDays(30)]),
                    'expired' => $q->whereNotNull('warranty_end')->where('warranty_end', '<', now()),
                    'none' => $q->whereNull('warranty_end'),
                    default => $q,
                };
            })
            ->when($request->get('purchase_from'), fn ($q, $v) => $q->whereDate('purchase_date', '>=', $v))
            ->when($request->get('purchase_to'), fn ($q, $v) => $q->whereDate('purchase_date', '<=', $v))
            ->orderBy($request->get('sort', 'name'), $request->get('direction', 'asc') === 'desc' ? 'desc' : 'asc')
            ->paginate(20)
            ->withQueryString();

        $categories = Asset::whereNotNull('category')->distinct()->orderBy('category')->pluck('category');
        $brands = Asset::whereNotNull('brand')->distinct()->orderBy('brand')->pluck('brand');
        $workOrders = WorkOrder::orderByDesc('created_at')->limit(200)->get();

        return view('assets.index', compact('assets', 'categories', 'brands', 'workOrders', 'showRemoved'));
    }

    public function create(): View
    {
        $workOrders = WorkOrder::orderByDesc('created_at')->limit(200)->get();

        return view('assets.create', compact('workOrders'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validateMasterDetails($request) + $request->validate([
            'status' => ['nullable', 'in:'.implode(',', Asset::STATUSES)],
            'current_location' => ['nullable', 'in:'.implode(',', Asset::LOCATIONS)],
            'current_work_order_id' => ['nullable', 'exists:work_orders,id'],
            'quantity' => ['nullable', 'integer', 'min:1'],
        ]);

        $quantity = $data['quantity'] ?? 1;

        $asset = Asset::create($data + [
            'status' => $data['status'] ?? 'available',
            'current_location' => $data['current_location'] ?? 'company_store',
            'quantity' => $quantity,
            'created_by' => $request->user()->id,
        ]);

        // Every asset starts its movement timeline with an auto-recorded
        // "purchase" entry (Supplier -> wherever it landed), already
        // confirmed - there's no separate party to confirm receipt of an
        // asset that's only just been entered into the system.
        AssetMovement::create([
            'asset_id' => $asset->id,
            'type' => 'purchase',
            'quantity' => $quantity,
            'from_location' => 'supplier',
            'to_location' => $asset->current_location,
            'to_work_order_id' => $asset->current_work_order_id,
            'status' => 'confirmed',
            'moved_at' => $asset->purchase_date ?? now()->toDateString(),
            'remarks' => 'Initial asset record.',
            'created_by' => $request->user()->id,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        // Seeds the ledger with the asset's full quantity, at wherever it
        // landed, under whatever status it was entered as (usually
        // Available, but an asset can be registered already damaged or
        // missing) - every later Movement/Repair/Verification/Status
        // Update moves quantity between buckets from here.
        AssetStock::adjust($asset, $asset->current_location, $asset->current_work_order_id, $asset->status, $quantity);

        if ($error = $this->attachFiles($asset, $request)) {
            return back()->withErrors(['attachments' => $error]);
        }

        return redirect()->route('assets.show', $asset)->with('success', "Asset {$asset->asset_code} created.");
    }

    public function show(Request $request, Asset $asset): View
    {
        $asset->load([
            'media', 'statusLogs.updatedBy', 'statusLogs.workOrder',
            'changeRequests.requestedBy', 'changeRequests.reviewedBy',
            'movements.fromWorkOrder', 'movements.toWorkOrder', 'movements.createdBy', 'movements.confirmedBy',
            'repairs.workOrder', 'repairs.createdBy', 'repairs.media',
            'verifications.workOrder', 'verifications.verifiedBy', 'verifications.media',
            'currentWorkOrder.site', 'createdBy',
            'stocks' => fn ($q) => $q->where('quantity', '>', 0)->with('workOrder'),
        ]);

        $user = $request->user();
        // Scoped below (once $ledWorkOrderIds/$statusUpdateStocks are known)
        // to "does this user have at least one bucket - any status except
        // in_transit - they're allowed to change the status of", not the
        // stale current_work_order_id single-value field.
        $canUpdateStatus = $user->can('assets.update_status');

        // null = unrestricted (Admin); otherwise the exact set of work order
        // IDs this user leads, used to decide both whether they can dispatch
        // this asset (must lead its current site) and whether they can
        // confirm any one pending movement (must lead its destination site).
        $ledWorkOrderIds = $user->hasRole('Admin') ? null : WorkOrder::whereHas('executiveTeams', fn ($q) => $q->whereNull('unassigned_at')
            ->whereHas('executiveTeam', fn ($q2) => $q2->where('team_leader_id', $user->id)))
            ->pluck('id');

        // The asset's stock can now sit at several work orders at once - a
        // Team Leader may act on it as soon as any of them is a site they
        // lead (company-store stock stays Admin/Management-only, same as
        // before: work_order_id is null there, so it never matches).
        $availableAtLedSite = $ledWorkOrderIds !== null && $asset->stocks
            ->where('status', 'available')
            ->pluck('work_order_id')
            ->filter()
            ->intersect($ledWorkOrderIds)
            ->isNotEmpty();

        $canCreateMovement = $user->can('movements.create') && (
            $ledWorkOrderIds === null || $user->hasRole('Management') || $availableAtLedSite
        );

        $canCreateRepair = $user->can('repairs.create') && (
            $ledWorkOrderIds === null || $user->hasRole('Management') || $availableAtLedSite
        );

        $canCreateVerification = $user->can('verifications.create') && (
            $ledWorkOrderIds === null || $user->hasRole('Management') || $availableAtLedSite
        );

        // Where this Team Leader may pick as the "at" location for a new
        // Repair/Verification, or as the source for a new Movement -
        // Admin/Management get every bucket that actually holds stock.
        $availableStocks = $asset->stocks->where('status', 'available')->where('quantity', '>', 0)
            ->when($ledWorkOrderIds !== null && ! $user->hasRole('Management'), fn ($stocks) => $stocks->filter(fn ($stock) => $stock->work_order_id && $ledWorkOrderIds->contains($stock->work_order_id)));

        // Every bucket (any status except in_transit, which is mid-flight
        // under an in-progress Movement and shouldn't be manually flipped)
        // this user may change the status of, quantity-wise - a TL only
        // gets buckets sitting at a work order they lead; Admin/Management
        // get every bucket the asset has stock in, wherever it is.
        $statusUpdateStocks = $asset->stocks->where('quantity', '>', 0)->where('status', '!=', 'in_transit')
            ->when($ledWorkOrderIds !== null && ! $user->hasRole('Management'), fn ($stocks) => $stocks->filter(fn ($stock) => $stock->work_order_id && $ledWorkOrderIds->contains($stock->work_order_id)));

        $canUpdateStatus = $canUpdateStatus && $statusUpdateStocks->isNotEmpty();

        return view('assets.show', compact('asset', 'canUpdateStatus', 'ledWorkOrderIds', 'canCreateMovement', 'canCreateRepair', 'canCreateVerification', 'availableStocks', 'statusUpdateStocks'));
    }

    public function edit(Asset $asset): View
    {
        $workOrders = WorkOrder::orderByDesc('created_at')->limit(200)->get();

        return view('assets.edit', compact('asset', 'workOrders'));
    }

    /**
     * Admin edits an asset's master details directly. Every other role that
     * holds assets.edit (Management, or anyone else an Admin grants it to)
     * goes through requestUpdate() instead - the asset's own values never
     * change until an Admin approves that request.
     *
     * quantity is deliberately kept out of validateMasterDetails() (and so
     * out of the change-request flow too) - AssetChangeRequestController::
     * approve() applies new_values with a raw Asset::update(), which would
     * silently desync Asset::quantity from the AssetStock ledger. Handling
     * it here, Admin-only, lets it go through AssetStock::adjust() instead
     * so the ledger stays the source of truth.
     */
    public function update(Request $request, Asset $asset): RedirectResponse
    {
        abort_unless($request->user()->hasRole('Admin'), 403, 'Only an Admin can apply asset changes directly. Submit this as a change request instead.');

        $data = $this->validateMasterDetails($request);

        $quantityData = $request->validate(['quantity' => ['nullable', 'integer', 'min:1']]);

        if (($quantityData['quantity'] ?? null) !== null) {
            $delta = $quantityData['quantity'] - $asset->quantity;

            if ($delta < 0) {
                $available = AssetStock::availableAt($asset, $asset->current_location, $asset->current_work_order_id);

                if ($available < abs($delta)) {
                    $where = $asset->current_location === 'work_order' && $asset->currentWorkOrder
                        ? $asset->currentWorkOrder->work_order_no
                        : ucwords(str_replace('_', ' ', $asset->current_location));

                    return back()->withErrors(['quantity' => "Can only reduce by up to {$available} - that's what's Available at {$where}. If the rest is elsewhere or in another state, adjust it there via a Movement, Repair, or Verification first."])->withInput();
                }
            }

            if ($delta !== 0) {
                AssetStock::adjust($asset, $asset->current_location, $asset->current_work_order_id, 'available', $delta);
            }
        }

        $asset->update($data);

        if ($error = $this->attachFiles($asset, $request)) {
            return back()->withErrors(['attachments' => $error]);
        }

        return redirect()->route('assets.show', $asset)->with('success', 'Asset updated.');
    }

    public function requestUpdate(Request $request, Asset $asset): RedirectResponse
    {
        $data = $this->validateMasterDetails($request);

        $oldValues = collect($data)->keys()->mapWithKeys(fn ($key) => [$key => $asset->{$key}])->all();

        AssetChangeRequest::create([
            'asset_id' => $asset->id,
            'requested_by' => $request->user()->id,
            'old_values' => $oldValues,
            'new_values' => $data,
            'status' => 'pending',
        ]);

        // Attachments/reports aren't "master details" under approval - they're
        // additive documentation, so anyone with assets.edit can add them
        // immediately regardless of whether their detail changes are pending.
        if ($error = $this->attachFiles($asset, $request)) {
            return back()->withErrors(['attachments' => $error]);
        }

        return redirect()->route('assets.show', $asset)->with('success', 'Change request submitted for Admin approval. The asset\'s current details remain active until then.');
    }

    private function validateMasterDetails(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:150'],
            'brand' => ['nullable', 'string', 'max:150'],
            'model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:150'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'supplier' => ['nullable', 'string', 'max:255'],
            'invoice_number' => ['nullable', 'string', 'max:150'],
            'warranty_start' => ['nullable', 'date'],
            'warranty_end' => ['nullable', 'date'],
            'warranty_provider' => ['nullable', 'string', 'max:255'],
            'warranty_card_details' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);
    }

    private function attachFiles(Asset $asset, Request $request): ?string
    {
        try {
            foreach ($request->file('attachments', []) as $file) {
                $asset->addMedia($file)->toMediaCollection('attachments');
            }
            foreach ($request->file('reports', []) as $file) {
                $asset->addMedia($file)->toMediaCollection('reports');
            }
        } catch (FileIsTooBig $e) {
            return 'One of those files is too large (max 50MB).';
        }

        return null;
    }

    public function destroyMedia(Asset $asset, Media $media): RedirectResponse
    {
        abort_unless((string) $media->model_id === (string) $asset->id, 404);

        $media->delete();

        return back()->with('success', 'File removed.');
    }

    /**
     * Quantity-wise, work-order-wise status update: moves a specific
     * amount of stock from one AssetStock bucket's current status to a
     * new one, via AssetStock::adjust() - exactly like AssetMovementController
     * moves quantity between locations. If the same asset also has stock
     * at other work orders, those buckets are never touched; only the
     * (location, work_order_id) bucket the caller picked moves.
     *
     * Asset::status/current_location/current_work_order_id (the legacy
     * single-value fields driving the page's badge and other list views)
     * are only synced when this change now accounts for the asset's
     * entire remaining quantity - otherwise they're left alone rather
     * than misrepresenting the bulk of the asset that didn't change, the
     * same staleness-avoidance rule AssetMovementController::confirm()
     * already follows.
     */
    public function updateStatus(Request $request, Asset $asset): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'location' => ['required', 'in:'.implode(',', Asset::LOCATIONS)],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'from_status' => ['required', 'in:'.implode(',', AssetStock::STATUSES)],
            'quantity' => ['required', 'integer', 'min:1'],
            'status' => ['required', 'in:'.implode(',', Asset::STATUSES)],
            'reason' => ['nullable', 'string'],
            'proof' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $workOrderId = $data['work_order_id'] ?? null;

        if (! $user->hasRole('Admin') && ! $user->hasRole('Management')) {
            abort_unless($this->isTeamLeaderOfWorkOrder($workOrderId, $user), 403, 'You can only update the status of assets assigned to a site you lead.');
        }

        if ($data['from_status'] === $data['status']) {
            return back()->withErrors(['status' => 'Pick a different status to change these units to.'])->withInput();
        }

        $bucketQuantity = AssetStock::quantityAt($asset, $data['location'], $workOrderId, $data['from_status']);

        if ($data['quantity'] > $bucketQuantity) {
            return back()->withErrors(['quantity' => "Only {$bucketQuantity} unit(s) are currently \"{$data['from_status']}\" there - adjust the quantity."])->withInput();
        }

        AssetStock::adjust($asset, $data['location'], $workOrderId, $data['from_status'], -$data['quantity']);
        AssetStock::adjust($asset, $data['location'], $workOrderId, $data['status'], $data['quantity']);

        $log = AssetStatusLog::create([
            'asset_id' => $asset->id,
            'previous_status' => $data['from_status'],
            'new_status' => $data['status'],
            'updated_by' => $user->id,
            'role' => $user->roles->pluck('name')->join(', ') ?: null,
            'work_order_id' => $workOrderId,
            'location' => $data['location'],
            'quantity' => $data['quantity'],
            'reason' => $data['reason'] ?? null,
        ]);

        if ($request->hasFile('proof')) {
            try {
                $log->addMedia($request->file('proof'))->toMediaCollection('proof');
            } catch (FileIsTooBig $e) {
                return back()->withErrors(['proof' => 'That file is too large (max 20MB).']);
            }
        }

        $asset->refresh();
        $elsewhere = $asset->stocks()->where('status', '!=', $data['status'])->sum('quantity');

        if ($elsewhere === 0) {
            $asset->update([
                'status' => $data['status'],
                'current_location' => $data['location'],
                'current_work_order_id' => $workOrderId,
            ]);
        }

        $where = $data['location'] === 'work_order' && $workOrderId
            ? (WorkOrder::find($workOrderId)?->work_order_no ?? 'that work order')
            : ucwords(str_replace('_', ' ', $data['location']));

        return back()->with('success', "Marked {$data['quantity']} unit(s) at {$where} as \"{$data['status']}\".");
    }

    public function destroy(Asset $asset): RedirectResponse
    {
        $asset->delete();

        return redirect()->route('assets.index')->with('success', 'Asset removed.');
    }

    public function restore(int $id): RedirectResponse
    {
        $asset = Asset::onlyTrashed()->findOrFail($id);
        $asset->restore();

        return redirect()->route('assets.show', $asset)->with('success', 'Asset restored.');
    }

    /**
     * Permanently deletes an already-removed asset - only reachable from
     * the Removed Assets list, so it can never be applied to an active
     * one. Every dependent record (movements, repairs, verifications,
     * stock ledger rows, status logs, change requests) cascade-deletes at
     * the database level along with it; equipment requests that reference
     * it just lose that reference (asset_id set to null) instead of being
     * deleted themselves.
     */
    public function forceDelete(int $id): RedirectResponse
    {
        $asset = Asset::onlyTrashed()->findOrFail($id);
        $asset->forceDelete();

        return redirect()->route('assets.index', ['removed' => 1])->with('success', 'Asset permanently deleted.');
    }

    public function pdf(Asset $asset)
    {
        $asset->load([
            'statusLogs.updatedBy', 'statusLogs.workOrder',
            'movements.fromWorkOrder', 'movements.toWorkOrder', 'movements.createdBy',
            'repairs.workOrder', 'repairs.createdBy',
            'verifications.workOrder', 'verifications.verifiedBy',
            'currentWorkOrder.site', 'createdBy',
        ]);

        $pdf = Pdf::loadView('assets.pdf', compact('asset'));

        return $pdf->download("{$asset->asset_code}.pdf");
    }

    public function stockPdf(Asset $asset)
    {
        $asset->load(['stocks' => fn ($q) => $q->where('quantity', '>', 0)->with('workOrder')]);

        $pdf = Pdf::loadView('assets.stock-pdf', compact('asset'));

        return $pdf->download("{$asset->asset_code}-stock-by-location.pdf");
    }

    /**
     * A dedicated, always-current view of every asset presently marked
     * "missing" - "Reported By" / "Reported Date" are pulled from that
     * asset's own Status History (the most recent entry that transitioned
     * it to missing), so nothing new needs to be tracked separately.
     */
    public function missing(Request $request): View
    {
        $user = $request->user();
        $ledWorkOrderIds = $user->hasRole('Admin') || $user->hasRole('Management') ? null : WorkOrder::whereHas('executiveTeams', fn ($q) => $q->whereNull('unassigned_at')
            ->whereHas('executiveTeam', fn ($q2) => $q2->where('team_leader_id', $user->id)))
            ->pluck('id');

        // Asset::status only reflects "missing" when the ENTIRE asset was
        // marked missing in one go - a Verification or Status Update can
        // now put just part of an asset's quantity into the "missing"
        // bucket at one work order while the rest stays available
        // elsewhere, which the legacy status field never captures. The
        // AssetStock ledger's "missing" buckets are what's authoritative.
        $assets = Asset::query()
            ->whereHas('stocks', fn ($q) => $q->where('status', 'missing')->where('quantity', '>', 0)
                ->when($ledWorkOrderIds !== null, fn ($q2) => $q2->whereIn('work_order_id', $ledWorkOrderIds)))
            ->with(['currentWorkOrder.site', 'statusLogs.updatedBy', 'stocks.workOrder'])
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('asset_code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")))
            ->when($request->get('work_order_id'), fn ($q, $v) => $q->whereHas('stocks', fn ($q2) => $q2
                ->where('work_order_id', $v)
                ->where('status', 'missing')
                ->where('quantity', '>', 0)))
            ->when($request->get('from') || $request->get('to'), fn ($q) => $q->whereHas('statusLogs', function ($q2) use ($request) {
                $q2->where('new_status', 'missing')
                    ->when($request->get('from'), fn ($q3, $v) => $q3->whereDate('created_at', '>=', $v))
                    ->when($request->get('to'), fn ($q3, $v) => $q3->whereDate('created_at', '<=', $v));
            }))
            ->orderBy($request->get('sort', 'name'), $request->get('direction', 'asc') === 'desc' ? 'desc' : 'asc')
            ->paginate(20)
            ->withQueryString();

        $assets->getCollection()->each(function (Asset $asset) use ($ledWorkOrderIds) {
            $asset->missingSince = $asset->statusLogs->firstWhere('new_status', 'missing');
            $asset->missingStocks = $asset->stocks
                ->where('status', 'missing')
                ->where('quantity', '>', 0)
                ->when($ledWorkOrderIds !== null, fn ($stocks) => $stocks->filter(fn ($stock) => $stock->work_order_id && $ledWorkOrderIds->contains($stock->work_order_id)));
            $asset->missingQuantity = $asset->missingStocks->sum('quantity');
        });

        $workOrders = $ledWorkOrderIds === null
            ? WorkOrder::orderByDesc('created_at')->limit(200)->get()
            : WorkOrder::whereIn('id', $ledWorkOrderIds)->get();

        return view('assets.missing', compact('assets', 'workOrders'));
    }

    public function missingPdf(Request $request)
    {
        $user = $request->user();
        $ledWorkOrderIds = $user->hasRole('Admin') || $user->hasRole('Management') ? null : WorkOrder::whereHas('executiveTeams', fn ($q) => $q->whereNull('unassigned_at')
            ->whereHas('executiveTeam', fn ($q2) => $q2->where('team_leader_id', $user->id)))
            ->pluck('id');

        $assets = Asset::query()
            ->whereHas('stocks', fn ($q) => $q->where('status', 'missing')->where('quantity', '>', 0)
                ->when($ledWorkOrderIds !== null, fn ($q2) => $q2->whereIn('work_order_id', $ledWorkOrderIds)))
            ->with(['currentWorkOrder.site', 'statusLogs.updatedBy', 'stocks.workOrder'])
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('asset_code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%")
                ->orWhere('category', 'like', "%{$search}%")))
            ->when($request->get('work_order_id'), fn ($q, $v) => $q->whereHas('stocks', fn ($q2) => $q2
                ->where('work_order_id', $v)
                ->where('status', 'missing')
                ->where('quantity', '>', 0)))
            ->when($request->get('from') || $request->get('to'), fn ($q) => $q->whereHas('statusLogs', function ($q2) use ($request) {
                $q2->where('new_status', 'missing')
                    ->when($request->get('from'), fn ($q3, $v) => $q3->whereDate('created_at', '>=', $v))
                    ->when($request->get('to'), fn ($q3, $v) => $q3->whereDate('created_at', '<=', $v));
            }))
            ->orderBy('name')
            ->get()
            ->each(function (Asset $asset) use ($ledWorkOrderIds) {
                $asset->missingSince = $asset->statusLogs->firstWhere('new_status', 'missing');
                $asset->missingStocks = $asset->stocks
                    ->where('status', 'missing')
                    ->where('quantity', '>', 0)
                    ->when($ledWorkOrderIds !== null, fn ($stocks) => $stocks->filter(fn ($stock) => $stock->work_order_id && $ledWorkOrderIds->contains($stock->work_order_id)));
                $asset->missingQuantity = $asset->missingStocks->sum('quantity');
            });

        $pdf = Pdf::loadView('assets.missing-pdf', compact('assets'));

        return $pdf->download('missing-equipment.pdf');
    }
}
