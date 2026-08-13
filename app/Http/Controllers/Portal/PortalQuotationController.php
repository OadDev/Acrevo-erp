<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalQuotationController extends Controller
{
    public function index(Request $request): View
    {
        $client = $request->user()->client();

        abort_unless($client, 403, 'No client account linked to this login.');

        $quotations = Quotation::where('client_id', $client->id)
            ->whereIn('status', ['sent', 'approved', 'rejected', 'expired'])
            ->latest()
            ->paginate(10);

        return view('portal.quotations.index', compact('quotations'));
    }

    public function show(Request $request, Quotation $quotation): View
    {
        $client = $request->user()->client();

        abort_unless($client && $quotation->client_id === $client->id, 403);
        abort_if($quotation->status === 'draft', 404);

        $quotation->load('items');

        return view('portal.quotations.show', compact('quotation'));
    }

    public function approve(Request $request, Quotation $quotation): RedirectResponse
    {
        $client = $request->user()->client();

        abort_unless($client && $quotation->client_id === $client->id, 403);
        abort_unless($quotation->status === 'sent', 422, 'This quotation is no longer awaiting your decision.');

        $quotation->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $client->name,
        ]);

        Site::firstOrCreate(
            ['quotation_id' => $quotation->id],
            [
                'client_id' => $quotation->client_id,
                'address' => $client->address,
                'city' => $client->city,
                'state' => $client->state,
                'pincode' => $client->pincode,
                'site_contact_name' => $client->name,
                'site_contact_phone' => $client->phone,
                'created_by' => $request->user()->id,
            ]
        );

        return redirect()->route('portal.quotations.show', $quotation)->with('success', 'Quotation accepted. Our team will follow up to schedule the work.');
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        $client = $request->user()->client();

        abort_unless($client && $quotation->client_id === $client->id, 403);
        abort_unless($quotation->status === 'sent', 422, 'This quotation is no longer awaiting your decision.');

        $data = $request->validate([
            'decision' => ['required', 'in:reject,requote'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $reason = $data['decision'] === 'requote'
            ? 'Re-quote requested: '.$data['reason']
            : $data['reason'];

        $quotation->update(['status' => 'rejected', 'rejected_reason' => $reason]);

        return redirect()->route('portal.quotations.index')->with('success', 'Your response has been sent to our team.');
    }
}
