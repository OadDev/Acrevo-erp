<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksSiteTeamLeadership;
use App\Http\Controllers\Concerns\LogsAssetStatusChanges;
use App\Models\Asset;
use App\Models\AssetStock;
use App\Models\AssetVerification;
use App\Models\WorkOrder;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class AssetVerificationController extends Controller
{
    use ChecksSiteTeamLeadership, LogsAssetStatusChanges;

    public function index(Request $request): View
    {
        $verifications = AssetVerification::query()
            ->with(['asset' => fn ($q) => $q->withTrashed(), 'workOrder', 'verifiedBy'])
            ->when($request->get('q'), fn ($q, $search) => $q->whereHas('asset', fn ($q2) => $q2
                ->where('asset_code', 'like', "%{$search}%")
                ->orWhere('name', 'like', "%{$search}%")
                ->orWhere('serial_number', 'like', "%{$search}%")))
            ->when($request->get('result'), fn ($q, $v) => $q->where('result', $v))
            ->when($request->get('work_order_id'), fn ($q, $v) => $q->where('work_order_id', $v))
            ->when($request->get('from'), fn ($q, $v) => $q->whereDate('verified_at', '>=', $v))
            ->when($request->get('to'), fn ($q, $v) => $q->whereDate('verified_at', '<=', $v))
            ->orderByDesc('verified_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        $workOrders = WorkOrder::orderByDesc('created_at')->limit(200)->get();

        return view('assets.verifications.index', compact('verifications', 'workOrders'));
    }

    /**
     * Records a physical check of an asset. Finding it missing or damaged
     * drives the asset's own operational status through the same shared
     * transition (and undeletable log) used by repairs and manual status
     * updates; a clean "verified_ok" result is logged but leaves the
     * asset's current status untouched.
     */
    public function store(Request $request, Asset $asset): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'result' => ['required', 'in:'.implode(',', AssetVerification::RESULTS)],
            'location' => ['required', 'in:'.implode(',', Asset::LOCATIONS)],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'condition' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string'],
            'verified_at' => ['required', 'date'],
            'proof' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        if (! $user->hasRole('Admin') && ! $user->hasRole('Management')) {
            abort_unless($this->isTeamLeaderOfWorkOrder($data['work_order_id'] ?? null, $user), 403, 'You can only verify assets at a site you lead.');
        }

        $available = AssetStock::availableAt($asset, $data['location'], $data['work_order_id'] ?? null);
        if ($data['quantity'] > $available) {
            return back()->withErrors(['quantity' => "Only {$available} available at that location."])->withInput();
        }

        $verification = AssetVerification::create($data + [
            'asset_id' => $asset->id,
            'verified_by' => $user->id,
        ]);

        $badBucket = match ($data['result']) {
            'not_found' => 'missing',
            'damaged' => 'damaged',
            default => null,
        };

        if ($badBucket) {
            AssetStock::adjust($asset, $data['location'], $data['work_order_id'] ?? null, 'available', -$data['quantity']);
            AssetStock::adjust($asset, $data['location'], $data['work_order_id'] ?? null, $badBucket, $data['quantity']);
        }

        // Legacy whole-asset status flag only flips when this verification
        // covers everything that was available there, mirroring the same
        // heuristic used by Movement::confirm() and repair reporting.
        if ($data['quantity'] === $available) {
            match ($data['result']) {
                'not_found' => $this->transitionAssetStatus($asset, 'missing', $user, "Verification #{$verification->id} found the asset missing."),
                'damaged' => $this->transitionAssetStatus($asset, 'damaged', $user, "Verification #{$verification->id} found the asset damaged."),
                default => null,
            };
        }

        if ($request->hasFile('proof')) {
            try {
                $verification->addMedia($request->file('proof'))->toMediaCollection('proof');
            } catch (FileIsTooBig $e) {
                return back()->withErrors(['proof' => 'That file is too large (max 20MB).']);
            }
        }

        return back()->with('success', 'Verification recorded.');
    }

    public function edit(AssetVerification $verification): View
    {
        $verification->load('asset');

        return view('assets.verifications.edit', compact('verification'));
    }

    /**
     * result/location/quantity/work_order_id are deliberately not editable
     * here - they already moved quantity between Asset Stock buckets when
     * this verification was recorded, and changing them after the fact
     * without re-deriving that ledger effect would desync it. Only the
     * descriptive fields can be corrected.
     */
    public function update(Request $request, AssetVerification $verification): RedirectResponse
    {
        $data = $request->validate([
            'condition' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string'],
            'verified_at' => ['required', 'date'],
        ]);

        $verification->update($data);

        return redirect()->route('assets.show', $verification->asset_id)->with('success', 'Verification updated.');
    }

    /**
     * Deleting a verification only removes the history entry - it does not
     * move quantity back to Available. Unlike a pending Movement or an
     * open Repair, a verification's Missing/Damaged finding isn't a
     * reservation waiting to be settled; it's a real-world fact as of that
     * check, and removing the record shouldn't silently un-report it.
     */
    public function destroy(AssetVerification $verification): RedirectResponse
    {
        $verification->delete();

        return back()->with('success', 'Verification entry removed.');
    }

    public function pdf(Asset $asset)
    {
        $asset->load(['verifications.workOrder', 'verifications.verifiedBy']);

        $pdf = Pdf::loadView('assets.verifications.pdf', compact('asset'));

        return $pdf->download("{$asset->asset_code}-verification-history.pdf");
    }
}
