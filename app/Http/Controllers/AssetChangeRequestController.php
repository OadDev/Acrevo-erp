<?php

namespace App\Http\Controllers;

use App\Models\AssetChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssetChangeRequestController extends Controller
{
    public function index(): View
    {
        $changeRequests = AssetChangeRequest::with(['asset', 'requestedBy', 'reviewedBy'])
            ->where('status', 'pending')
            ->latest()
            ->paginate(20);

        return view('assets.change-requests.index', compact('changeRequests'));
    }

    public function approve(Request $request, AssetChangeRequest $changeRequest): RedirectResponse
    {
        abort_unless($changeRequest->status === 'pending', 422, 'This change request has already been reviewed.');

        $data = $request->validate(['remarks' => ['nullable', 'string']]);

        $changeRequest->asset->update($changeRequest->new_values);

        $changeRequest->update([
            'status' => 'approved',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'remarks' => $data['remarks'] ?? null,
        ]);

        return back()->with('success', 'Change request approved and applied to the asset.');
    }

    public function reject(Request $request, AssetChangeRequest $changeRequest): RedirectResponse
    {
        abort_unless($changeRequest->status === 'pending', 422, 'This change request has already been reviewed.');

        $data = $request->validate(['remarks' => ['nullable', 'string']]);

        $changeRequest->update([
            'status' => 'rejected',
            'reviewed_by' => $request->user()->id,
            'reviewed_at' => now(),
            'remarks' => $data['remarks'] ?? null,
        ]);

        return back()->with('success', 'Change request rejected. The asset\'s original details remain unchanged.');
    }
}
