<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuotationRequest;
use App\Models\Enquiry;
use App\Models\Quotation;
use App\Models\Site;
use App\Services\ConversationService;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class QuotationController extends Controller
{
    public function index(Request $request): View
    {
        $quotations = Quotation::query()
            ->with(['client', 'enquiry', 'workOrders'])
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('quotations.index', compact('quotations'));
    }

    public function create(Request $request): View
    {
        $enquiry = Enquiry::with('client')->findOrFail($request->get('enquiry_id'));

        return view('quotations.create', compact('enquiry'));
    }

    public function store(QuotationRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $quotation = DB::transaction(function () use ($data, $request) {
            $quotation = Quotation::create([
                'enquiry_id' => $data['enquiry_id'],
                'client_id' => $data['client_id'],
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'] ?? 0,
                'tax_percent' => $data['tax_percent'] ?? 0,
                'terms' => $data['terms'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($quotation, $data['items']);
            $quotation->recalculateTotals();
            $quotation->enquiry->update(['status' => 'quoted']);

            return $quotation;
        });

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation created successfully.');
    }

    public function show(Quotation $quotation): View
    {
        $quotation->load(['items', 'enquiry', 'client', 'revisions', 'workOrders']);
        $discussion = app(ConversationService::class)->discussionFor($quotation, auth()->user());

        return view('quotations.show', compact('quotation', 'discussion'));
    }

    public function edit(Quotation $quotation): View
    {
        $quotation->load('items');
        $enquiry = $quotation->enquiry;

        return view('quotations.edit', compact('quotation', 'enquiry'));
    }

    public function update(QuotationRequest $request, Quotation $quotation): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($quotation, $data) {
            $quotation->update([
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'] ?? 0,
                'tax_percent' => $data['tax_percent'] ?? 0,
                'terms' => $data['terms'] ?? null,
                'valid_until' => $data['valid_until'] ?? null,
            ]);

            $quotation->items()->delete();
            $this->syncItems($quotation, $data['items']);
            $quotation->recalculateTotals();
        });

        return redirect()->route('quotations.show', $quotation)->with('success', 'Quotation updated successfully.');
    }

    public function send(Quotation $quotation): RedirectResponse
    {
        $quotation->update(['status' => 'sent']);

        return back()->with('success', 'Quotation marked as sent to client.');
    }

    public function approve(Request $request, Quotation $quotation): RedirectResponse
    {
        $data = $request->validate(['approved_by' => ['nullable', 'string', 'max:255']]);

        $quotation->update([
            'status' => 'approved',
            'approved_at' => now(),
            'approved_by' => $data['approved_by'] ?? $quotation->client->name,
        ]);

        Site::firstOrCreate(
            ['quotation_id' => $quotation->id],
            [
                'client_id' => $quotation->client_id,
                'address' => $quotation->client->address,
                'city' => $quotation->client->city,
                'state' => $quotation->client->state,
                'pincode' => $quotation->client->pincode,
                'site_contact_name' => $quotation->client->name,
                'site_contact_phone' => $quotation->client->phone,
                'created_by' => $request->user()->id,
            ]
        );

        return back()->with('success', 'Quotation approved and site created. You can now generate the Work Order.');
    }

    public function reject(Request $request, Quotation $quotation): RedirectResponse
    {
        $data = $request->validate(['rejected_reason' => ['required', 'string']]);

        $quotation->update(['status' => 'rejected'] + $data);

        return back()->with('success', 'Quotation marked as rejected.');
    }

    public function revise(Quotation $quotation): RedirectResponse
    {
        $revision = DB::transaction(function () use ($quotation) {
            $new = $quotation->replicate(['status', 'approved_at', 'approved_by', 'rejected_reason', 'pdf_path']);
            $new->version = $quotation->version + 1;
            $new->parent_quotation_id = $quotation->id;
            $new->status = 'draft';
            $new->save();

            foreach ($quotation->items as $item) {
                $new->items()->create($item->only([
                    'item_type', 'name', 'description', 'unit', 'quantity',
                    'unit_price', 'discount', 'tax_percent', 'total', 'sort_order',
                ]));
            }

            $new->recalculateTotals();

            return $new;
        });

        return redirect()->route('quotations.edit', $revision)->with('success', 'New revision created. Update the details below.');
    }

    public function destroy(Quotation $quotation): RedirectResponse
    {
        $this->authorizeAdminOnly();

        $remainingWorkOrders = $quotation->workOrders()->count();
        abort_if($remainingWorkOrders > 0, 422, "This quotation has {$remainingWorkOrders} work order(s). Remove them before removing the quotation.");

        $quotation->delete();

        return redirect()->route('quotations.index')->with('success', 'Quotation removed.');
    }

    public function pdf(Quotation $quotation)
    {
        $quotation->load(['items', 'client', 'enquiry']);

        $pdf = Pdf::loadView('quotations.pdf', compact('quotation'));

        return $pdf->download("{$quotation->quotation_no}.pdf");
    }

    private function syncItems(Quotation $quotation, array $items): void
    {
        foreach ($items as $index => $item) {
            // Pre-tax line total - tax is applied once, at the quotation
            // level, in recalculateTotals(). Baking it in here too would
            // double-apply it (subtotal would already be tax-inclusive).
            $lineTotal = ($item['quantity'] * $item['unit_price']) - ($item['discount'] ?? 0);
            $taxPercent = $item['tax_percent'] ?? $quotation->tax_percent;

            $quotation->items()->create($item + [
                'discount' => $item['discount'] ?? 0,
                'tax_percent' => $taxPercent,
                'total' => round($lineTotal, 2),
                'sort_order' => $index,
            ]);
        }
    }
}
