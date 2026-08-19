<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalWorkOrderController extends Controller
{
    public function index(Request $request): View
    {
        $client = $request->user()->client();

        abort_unless($client, 403, 'No client account linked to this login.');

        $workOrders = WorkOrder::where('client_id', $client->id)->latest()->paginate(10);

        return view('portal.work-orders.index', compact('workOrders'));
    }

    public function show(WorkOrder $workOrder): View
    {
        $this->authorize('view', $workOrder);

        $workOrder->load([
            'dailyProgressReports' => fn ($q) => $q->latest(), 'media', 'tickets', 'clientReviews', 'completionCertificates',
            'approvalRequests.requestedBy', 'approvalRequests.requestedByClient', 'approvalRequests.respondedBy', 'approvalRequests.media', 'approvalRequests.workOrder.client',
            'summaries' => fn ($q) => $q->orderBy('entry_date'),
        ]);

        return view('portal.work-orders.show', compact('workOrder'));
    }

    public function accept(WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('view', $workOrder);

        abort_unless($workOrder->status === 'client_review', 422, 'This work order is not awaiting your review.');

        $workOrder->transitionTo('completed', 'Client reviewed and accepted the completed work.');

        $workOrder->completionCertificates()->create([
            'issued_date' => now(),
            'issued_by' => auth()->id(),
        ]);

        return redirect()->route('portal.work-orders.show', $workOrder)->with('success', 'Thank you — the work order is now marked complete.');
    }
}
