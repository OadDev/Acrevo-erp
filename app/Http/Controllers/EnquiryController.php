<?php

namespace App\Http\Controllers;

use App\Http\Requests\EnquiryRequest;
use App\Models\Client;
use App\Models\Enquiry;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EnquiryController extends Controller
{
    public function index(Request $request): View
    {
        $enquiries = Enquiry::query()
            ->with(['client', 'assignedTo'])
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('contact_name', 'like', "%{$search}%")
                ->orWhere('enquiry_no', 'like', "%{$search}%")
                ->orWhere('contact_phone', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('enquiries.index', compact('enquiries'));
    }

    public function create(Request $request): View
    {
        $client = $request->get('client_id') ? Client::find($request->get('client_id')) : null;
        $salesUsers = User::permission('enquiries.view')->get();

        return view('enquiries.create', compact('client', 'salesUsers'));
    }

    public function store(EnquiryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if (empty($data['client_id'])) {
            $client = Client::create([
                'name' => $data['contact_name'],
                'phone' => $data['contact_phone'],
                'email' => $data['contact_email'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'source' => $data['source'],
                'assigned_sales_user_id' => $data['assigned_to'] ?? $request->user()->id,
                'created_by' => $request->user()->id,
            ]);
            $data['client_id'] = $client->id;
        }

        $enquiry = Enquiry::create($data + ['created_by' => $request->user()->id, 'status' => 'new']);

        return redirect()->route('enquiries.show', $enquiry)->with('success', 'Enquiry created successfully.');
    }

    public function show(Enquiry $enquiry): View
    {
        $enquiry->load(['client.clientLogin.user', 'assignedTo', 'followUps.user', 'siteVisits.assignedTo', 'quotations', 'workOrders']);

        return view('enquiries.show', compact('enquiry'));
    }

    public function edit(Enquiry $enquiry): View
    {
        $salesUsers = User::permission('enquiries.view')->get();

        return view('enquiries.edit', compact('enquiry', 'salesUsers'));
    }

    public function update(EnquiryRequest $request, Enquiry $enquiry): RedirectResponse
    {
        $data = $request->validated();
        $enquiry->update($data);

        // The Client record is a separate row from the enquiry's own contact_*
        // fields, so editing "the client's email/address" here never used to
        // reach it. Fill in whatever the Client is still missing - never
        // overwrite a value it already has, since the enquiry's contact may
        // legitimately differ from the client's own registered details.
        $client = $enquiry->client;
        $missing = array_filter([
            'email' => $client->email ? null : ($data['contact_email'] ?? null),
            'address' => $client->address ? null : ($data['address'] ?? null),
            'city' => $client->city ? null : ($data['city'] ?? null),
        ]);
        if ($missing) {
            $client->update($missing);
        }

        return redirect()->route('enquiries.show', $enquiry)->with('success', 'Enquiry updated successfully.');
    }

    public function destroy(Enquiry $enquiry): RedirectResponse
    {
        $enquiry->delete();

        return redirect()->route('enquiries.index')->with('success', 'Enquiry deleted.');
    }
}
