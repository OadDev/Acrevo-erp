<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\Item;
use App\Models\TaxInvoice;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class TaxInvoiceController extends Controller
{
    public function index(Request $request): View
    {
        $taxInvoices = TaxInvoice::query()
            ->with(['company', 'client'])
            ->when($request->get('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->get('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('tax_invoice_no', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', "%{$search}%"))))
            ->latest('document_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $companies = Company::where('is_active', true)->orderBy('name')->get();

        return view('tax-invoices.index', compact('taxInvoices', 'companies'));
    }

    public function create(): View
    {
        return view('tax-invoices.create', [
            'companies' => Company::where('is_active', true)->orderBy('name')->get(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'items' => Item::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $taxInvoice = DB::transaction(function () use ($data, $request) {
            $taxInvoice = TaxInvoice::create([
                ...collect($data)->except('items')->all(),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($taxInvoice, $data['items']);
            $taxInvoice->recalculateTotals();

            return $taxInvoice;
        });

        return redirect()->route('tax-invoices.show', $taxInvoice)->with('success', 'Tax Invoice created.');
    }

    public function show(TaxInvoice $taxInvoice): View
    {
        $taxInvoice->load(['items', 'company', 'client', 'proformaInvoice', 'createdBy']);

        return view('tax-invoices.show', compact('taxInvoice'));
    }

    public function edit(TaxInvoice $taxInvoice): View
    {
        $taxInvoice->load('items');

        return view('tax-invoices.edit', [
            'taxInvoice' => $taxInvoice,
            'companies' => Company::where('is_active', true)->orderBy('name')->get(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'items' => Item::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, TaxInvoice $taxInvoice): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($taxInvoice, $data) {
            $taxInvoice->update(collect($data)->except('items')->all());
            $taxInvoice->items()->delete();
            $this->syncItems($taxInvoice, $data['items']);
            $taxInvoice->recalculateTotals();
        });

        return redirect()->route('tax-invoices.show', $taxInvoice)->with('success', 'Tax Invoice updated.');
    }

    public function destroy(TaxInvoice $taxInvoice): RedirectResponse
    {
        $taxInvoice->delete();

        return redirect()->route('tax-invoices.index')->with('success', 'Tax Invoice removed.');
    }

    public function pdf(TaxInvoice $taxInvoice)
    {
        $taxInvoice->load(['items', 'company', 'client']);

        $pdf = Pdf::loadView('tax-invoices.pdf', compact('taxInvoice'));

        return $pdf->download("{$taxInvoice->tax_invoice_no}.pdf");
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

    private function syncItems(TaxInvoice $taxInvoice, array $items): void
    {
        foreach ($items as $index => $item) {
            $taxInvoice->items()->create([
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
