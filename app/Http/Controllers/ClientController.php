<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(Request $request): View
    {
        $clients = Client::query()
            ->with('assignedSales')
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")
                ->orWhere('client_code', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('clients.index', compact('clients'));
    }

    public function create(): View
    {
        $salesUsers = User::role('Sales')->get();

        return view('clients.create', compact('salesUsers'));
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        $client = Client::create($request->validated() + ['created_by' => $request->user()->id]);

        return redirect()->route('clients.show', $client)->with('success', 'Client created successfully.');
    }

    public function show(Client $client): View
    {
        $client->load(['contacts', 'enquiries', 'workOrders', 'invoices.payments']);

        return view('clients.show', compact('client'));
    }

    public function edit(Client $client): View
    {
        $salesUsers = User::role('Sales')->get();

        return view('clients.edit', compact('client', 'salesUsers'));
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()->route('clients.show', $client)->with('success', 'Client updated successfully.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        $client->delete();

        return redirect()->route('clients.index')->with('success', 'Client removed.');
    }
}
