<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
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

        $workOrder->load(['dailyProgressReports' => fn ($q) => $q->latest(), 'media', 'tickets', 'clientReviews', 'completionCertificates']);

        return view('portal.work-orders.show', compact('workOrder'));
    }
}
