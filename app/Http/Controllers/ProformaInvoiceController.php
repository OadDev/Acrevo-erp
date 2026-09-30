<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\Item;
use App\Models\ProformaInvoice;
use App\Models\TaxInvoice;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProformaInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $proformaInvoices = ProformaInvoice::query()
            ->with(['company', 'client'])
            ->when($request->get('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->get('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('proforma_no', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', "%{$search}%"))))
            ->latest('document_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $companies = Company::where('is_active', true)->orderBy('name')->get();

        return view('proforma-invoices.index', compact('proformaInvoices', 'companies'));
    }

    public function create(): View
    {
        return view('proforma-invoices.create', [
            'companies' => Company::where('is_active', true)->orderBy('name')->get(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'items' => Item::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $proformaInvoice = DB::transaction(function () use ($data, $request) {
            $proformaInvoice = ProformaInvoice::create([
                ...collect($data)->except('items')->all(),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($proformaInvoice, $data['items']);
            $proformaInvoice->recalculateTotals();

            return $proformaInvoice;
        });

        return redirect()->route('proforma-invoices.show', $proformaInvoice)->with('success', 'Proforma Invoice created.');
    }

    public function show(ProformaInvoice $proformaInvoice): View
    {
        $proformaInvoice->load(['items', 'company', 'client', 'convertedToTaxInvoice', 'createdBy']);

        return view('proforma-invoices.show', compact('proformaInvoice'));
    }

    public function edit(ProformaInvoice $proformaInvoice): View
    {
        abort_if($proformaInvoice->isConverted(), 422, 'This Proforma Invoice has already been converted to a Tax Invoice and can no longer be edited.');

        $proformaInvoice->load('items');

        return view('proforma-invoices.edit', [
            'proformaInvoice' => $proformaInvoice,
            'companies' => Company::where('is_active', true)->orderBy('name')->get(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'items' => Item::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, ProformaInvoice $proformaInvoice): RedirectResponse
    {
        abort_if($proformaInvoice->isConverted(), 422, 'This Proforma Invoice has already been converted to a Tax Invoice and can no longer be edited.');

        $data = $this->validated($request);

        DB::transaction(function () use ($proformaInvoice, $data) {
            $proformaInvoice->update(collect($data)->except('items')->all());
            $proformaInvoice->items()->delete();
            $this->syncItems($proformaInvoice, $data['items']);
            $proformaInvoice->recalculateTotals();
        });

        return redirect()->route('proforma-invoices.show', $proformaInvoice)->with('success', 'Proforma Invoice updated.');
    }

    public function destroy(ProformaInvoice $proformaInvoice): RedirectResponse
    {
        abort_if($proformaInvoice->isConverted(), 422, 'This Proforma Invoice has already been converted to a Tax Invoice and cannot be removed.');

        $proformaInvoice->delete();

        return redirect()->route('proforma-invoices.index')->with('success', 'Proforma Invoice removed.');
    }

    /**
     * Full 1:1 conversion - every line item is copied as-is into a new
     * Tax Invoice with its own number, and this Proforma is locked
     * (isConverted() becomes true) so it can't be edited or converted
     * again.
     */
    public function convert(Request $request, ProformaInvoice $proformaInvoice): RedirectResponse
    {
        abort_if($proformaInvoice->isConverted(), 422, 'This Proforma Invoice has already been converted.');

        $proformaInvoice->load('items');

        $taxInvoice = DB::transaction(function () use ($proformaInvoice, $request) {
            $taxInvoice = TaxInvoice::create([
                'company_id' => $proformaInvoice->company_id,
                'client_id' => $proformaInvoice->client_id,
                'document_date' => now()->toDateString(),
                'delivery_note' => $proformaInvoice->delivery_note,
                'payment_terms' => $proformaInvoice->payment_terms,
                'supplier_ref' => $proformaInvoice->supplier_ref,
                'other_reference' => $proformaInvoice->other_reference,
                'buyer_order_no' => $proformaInvoice->buyer_order_no,
                'buyer_order_date' => $proformaInvoice->buyer_order_date,
                'dispatch_doc_no' => $proformaInvoice->dispatch_doc_no,
                'dispatch_through' => $proformaInvoice->dispatch_through,
                'destination' => $proformaInvoice->destination,
                'terms_of_delivery' => $proformaInvoice->terms_of_delivery,
                'tax_percent' => $proformaInvoice->tax_percent,
                'status' => 'draft',
                'notes' => $proformaInvoice->notes,
                'proforma_invoice_id' => $proformaInvoice->id,
                'created_by' => $request->user()->id,
            ]);

            foreach ($proformaInvoice->items as $item) {
                $taxInvoice->items()->create($item->only(['item_id', 'name', 'hsn_sac_code', 'unit', 'quantity', 'rate', 'total', 'sort_order']));
            }

            $taxInvoice->recalculateTotals();

            $proformaInvoice->update(['status' => 'converted', 'converted_to_tax_invoice_id' => $taxInvoice->id]);

            return $taxInvoice;
        });

        return redirect()->route('tax-invoices.show', $taxInvoice)->with('success', 'Converted to Tax Invoice '.$taxInvoice->tax_invoice_no.'.');
    }

    public function pdf(ProformaInvoice $proformaInvoice)
    {
        $proformaInvoice->load(['items', 'company', 'client']);

        $pdf = Pdf::loadView('proforma-invoices.pdf', compact('proformaInvoice'));

        return $pdf->download("{$proformaInvoice->proforma_no}.pdf");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'document_date' => ['required', 'date'],
            'delivery_note' => ['nullable', 'string', 'max:255'],
            'payment_terms' => ['nullable', 'string', 'max:255'],
            'supplier_ref' => ['nullable', 'string', 'max:255'],
            'other_reference' => ['nullable', 'string', 'max:255'],
            'buyer_order_no' => ['nullable', 'string', 'max:255'],
            'buyer_order_date' => ['nullable', 'date'],
            'dispatch_doc_no' => ['nullable', 'string', 'max:255'],
            'dispatch_through' => ['nullable', 'string', 'max:255'],
            'destination' => ['nullable', 'string', 'max:255'],
            'terms_of_delivery' => ['nullable', 'string'],
            'tax_percent' => ['required', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', 'exists:items,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.hsn_sac_code' => ['nullable', 'string', 'max:30'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.rate' => ['required', 'numeric', 'min:0'],
        ]);
    }

    private function syncItems(ProformaInvoice $proformaInvoice, array $items): void
    {
        foreach ($items as $index => $item) {
            $proformaInvoice->items()->create([
                'item_id' => $item['item_id'] ?? null,
                'name' => $item['name'],
                'hsn_sac_code' => $item['hsn_sac_code'] ?? null,
                'unit' => $item['unit'],
                'quantity' => $item['quantity'],
                'rate' => $item['rate'],
                'total' => round($item['quantity'] * $item['rate'], 2),
                'sort_order' => $index,
            ]);
        }
    }
}
