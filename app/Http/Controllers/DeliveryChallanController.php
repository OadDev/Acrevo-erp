<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Company;
use App\Models\DeliveryChallan;
use App\Models\Item;
use App\Support\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class DeliveryChallanController extends Controller
{
    public function index(Request $request): View
    {
        $deliveryChallans = DeliveryChallan::query()
            ->with(['company', 'client'])
            ->when($request->get('company_id'), fn ($q, $v) => $q->where('company_id', $v))
            ->when($request->get('status'), fn ($q, $v) => $q->where('status', $v))
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('challan_no', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', "%{$search}%"))))
            ->latest('challan_date')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $companies = Company::where('is_active', true)->orderBy('name')->get();

        return view('delivery-challans.index', compact('deliveryChallans', 'companies'));
    }

    public function create(): View
    {
        return view('delivery-challans.create', [
            'companies' => Company::where('is_active', true)->orderBy('name')->get(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'items' => Item::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $deliveryChallan = DB::transaction(function () use ($data, $request) {
            $deliveryChallan = DeliveryChallan::create([
                ...collect($data)->except('items')->all(),
                'status' => 'draft',
                'created_by' => $request->user()->id,
            ]);

            $this->syncItems($deliveryChallan, $data['items']);

            return $deliveryChallan;
        });

        return redirect()->route('delivery-challans.show', $deliveryChallan)->with('success', 'Delivery Challan created.');
    }

    public function show(DeliveryChallan $deliveryChallan): View
    {
        $deliveryChallan->load(['items', 'company', 'client', 'createdBy']);

        return view('delivery-challans.show', compact('deliveryChallan'));
    }

    public function edit(DeliveryChallan $deliveryChallan): View
    {
        $deliveryChallan->load('items');

        return view('delivery-challans.edit', [
            'deliveryChallan' => $deliveryChallan,
            'companies' => Company::where('is_active', true)->orderBy('name')->get(),
            'clients' => Client::where('is_active', true)->orderBy('name')->get(),
            'items' => Item::where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, DeliveryChallan $deliveryChallan): RedirectResponse
    {
        $data = $this->validated($request);

        DB::transaction(function () use ($deliveryChallan, $data) {
            $deliveryChallan->update(collect($data)->except('items')->all());
            $deliveryChallan->items()->delete();
            $this->syncItems($deliveryChallan, $data['items']);
        });

        return redirect()->route('delivery-challans.show', $deliveryChallan)->with('success', 'Delivery Challan updated.');
    }

    public function destroy(DeliveryChallan $deliveryChallan): RedirectResponse
    {
        $deliveryChallan->delete();

        return redirect()->route('delivery-challans.index')->with('success', 'Delivery Challan removed.');
    }

    public function pdf(DeliveryChallan $deliveryChallan)
    {
        $deliveryChallan->load(['items', 'company', 'client']);

        $pdf = Pdf::loadView('delivery-challans.pdf', compact('deliveryChallan'));

        return $pdf->download("{$deliveryChallan->challan_no}.pdf");
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'company_id' => ['required', 'exists:companies,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'challan_date' => ['required', 'date'],
            'delivery_time' => ['nullable', 'string', 'max:50'],
            'shipping_name' => ['nullable', 'string', 'max:255'],
            'shipping_address' => ['nullable', 'string'],
            'shipping_phone' => ['nullable', 'string', 'max:30'],
            'shipping_email' => ['nullable', 'email', 'max:255'],
            'shipping_tax_id' => ['nullable', 'string', 'max:30'],
            'terms_and_conditions' => ['nullable', 'string'],
            'status' => ['nullable', 'in:'.implode(',', \App\Models\DeliveryChallan::STATUSES)],
            'received_by_name' => ['nullable', 'string', 'max:255'],
            'received_by_comment' => ['nullable', 'string'],
            'received_by_date' => ['nullable', 'date'],
            'delivered_by_name' => ['nullable', 'string', 'max:255'],
            'delivered_by_comment' => ['nullable', 'string'],
            'delivered_by_date' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['nullable', 'exists:items,id'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.hsn_sac_code' => ['nullable', 'string', 'max:30'],
            'items.*.unit' => ['required', 'string', 'max:30'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
        ]);
    }

    private function syncItems(DeliveryChallan $deliveryChallan, array $items): void
    {
        foreach ($items as $index => $item) {
            $deliveryChallan->items()->create([
                'item_id' => $item['item_id'] ?? null,
                'name' => $item['name'],
                'hsn_sac_code' => $item['hsn_sac_code'] ?? null,
                'unit' => $item['unit'],
                'quantity' => $item['quantity'],
                'sort_order' => $index,
            ]);
        }
    }
}
