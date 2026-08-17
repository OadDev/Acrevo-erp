<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteController extends Controller
{
    public function index(Request $request): View
    {
        $sites = Site::query()
            ->with('client')
            ->withCount('workOrders')
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('site_no', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', "%{$search}%"))))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('sites.index', compact('sites'));
    }

    public function show(Site $site): View
    {
        $site->load(['client', 'quotation', 'media']);
        $workOrders = $site->workOrders()->latest()->paginate(15);

        return view('sites.show', compact('site', 'workOrders'));
    }

    public function create(Request $request): View
    {
        $client = $request->get('client_id') ? Client::findOrFail($request->get('client_id')) : null;
        $clients = Client::orderBy('name')->get();

        return view('sites.create', compact('client', 'clients'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'site_contact_name' => ['nullable', 'string', 'max:150'],
            'site_contact_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $site = Site::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('sites.show', $site)->with('success', 'Site added to client.');
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'site_contact_name' => ['nullable', 'string', 'max:150'],
            'site_contact_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $site->update($data);

        return back()->with('success', 'Site details updated.');
    }

    public function complete(Site $site): RedirectResponse
    {
        abort_if($site->status === 'completed', 422, 'This site is already marked completed.');

        $openWorkOrders = $site->workOrders()->whereNotIn('status', ['completed', 'cancelled'])->count();

        if ($openWorkOrders > 0) {
            return back()->withErrors(['site' => "This site still has {$openWorkOrders} work order(s) that aren't completed or cancelled yet. Finish or cancel them before marking the site completed."]);
        }

        $site->update(['status' => 'completed', 'completed_at' => now()]);

        return back()->with('success', 'Site marked as completed and handed over to the client.');
    }
}
