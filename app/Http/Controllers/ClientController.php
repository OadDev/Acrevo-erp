<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use App\Models\ClientLogin;
use App\Models\User;
use App\Services\ConversationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
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
        $client->load(['contacts', 'enquiries', 'workOrders', 'sites', 'invoices.payments', 'clientLogin.user']);
        $discussion = app(ConversationService::class)->discussionFor($client, auth()->user());

        return view('clients.show', compact('client', 'discussion'));
    }

    public function generatePortalAccess(Request $request, Client $client): RedirectResponse
    {
        abort_if($client->clientLogin, 422, 'This client already has portal access.');

        if (! $client->email) {
            return back()->withErrors(['email' => 'This client needs an email address before portal access can be created.']);
        }

        $password = Str::password(12);
        $hashedPassword = Hash::make($password);

        // users.email is unique at the database level with no exception for
        // soft-deleted rows, so firstOrCreate() would throw on a trashed match
        // (its lookup skips trashed rows, then the insert hits the constraint).
        $user = User::onlyTrashed()->where('email', $client->email)->first();
        if ($user) {
            $user->restore();
        }

        $user ??= User::firstOrCreate(
            ['email' => $client->email],
            [
                'name' => $client->name,
                'phone' => $client->phone,
                'password' => $hashedPassword,
                'is_active' => true,
                'must_change_password' => true,
                'created_by' => $request->user()->id,
            ]
        );

        $user->update([
            'name' => $client->name,
            'phone' => $client->phone,
            'password' => $hashedPassword,
            'is_active' => true,
            'must_change_password' => true,
        ]);
        $user->syncRoles(['Client']);

        ClientLogin::firstOrCreate(['client_id' => $client->id], ['user_id' => $user->id]);

        return redirect()->route('clients.show', $client)
            ->with('success', "Portal access created. Login: {$client->email} / Temporary password: {$password}");
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

    public function updatePortalPermissions(Request $request, Client $client): RedirectResponse
    {
        $data = $request->validate([
            'visible_sections' => ['nullable', 'array'],
            'visible_sections.*' => ['in:'.implode(',', array_keys(\App\Support\ClientPortalSections::SECTIONS))],
            'unrestricted' => ['nullable', 'boolean'],
        ]);

        // "Unrestricted" stores null (every section visible) rather than the
        // full key list, so a section added to the canonical list later is
        // visible here too without having to revisit every client.
        $client->update([
            'visible_sections' => $request->boolean('unrestricted') ? null : ($data['visible_sections'] ?? []),
        ]);

        return redirect()->route('clients.show', $client)->with('success', 'Portal section permissions updated.');
    }
}
