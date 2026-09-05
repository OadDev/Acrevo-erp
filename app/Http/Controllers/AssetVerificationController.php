<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ChecksSiteTeamLeadership;
use App\Http\Controllers\Concerns\LogsAssetStatusChanges;
use App\Models\Asset;
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

        if (! $user->hasRole('Admin') && ! $user->hasRole('Management')) {
            abort_unless($this->isTeamLeaderOfWorkOrder($asset->current_work_order_id, $user), 403, 'You can only verify assets at a site you lead.');
        }

        $data = $request->validate([
            'result' => ['required', 'in:'.implode(',', AssetVerification::RESULTS)],
            'condition' => ['nullable', 'string', 'max:150'],
            'remarks' => ['nullable', 'string'],
            'verified_at' => ['required', 'date'],
            'proof' => ['nullable', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf'],
        ]);

        $verification = AssetVerification::create($data + [
            'asset_id' => $asset->id,
            'work_order_id' => $asset->current_work_order_id,
            'verified_by' => $user->id,
        ]);

        match ($data['result']) {
            'not_found' => $this->transitionAssetStatus($asset, 'missing', $user, "Verification #{$verification->id} found the asset missing."),
            'damaged' => $this->transitionAssetStatus($asset, 'damaged', $user, "Verification #{$verification->id} found the asset damaged."),
            default => null,
        };

        if ($request->hasFile('proof')) {
            try {
                $verification->addMedia($request->file('proof'))->toMediaCollection('proof');
            } catch (FileIsTooBig $e) {
                return back()->withErrors(['proof' => 'That file is too large (max 20MB).']);
            }
        }

        return back()->with('success', 'Verification recorded.');
    }

    public function pdf(Asset $asset)
    {
        $asset->load(['verifications.workOrder', 'verifications.verifiedBy']);

        $pdf = Pdf::loadView('assets.verifications.pdf', compact('asset'));

        return $pdf->download("{$asset->asset_code}-verification-history.pdf");
    }
}
