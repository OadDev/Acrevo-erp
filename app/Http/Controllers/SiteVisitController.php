<?php

namespace App\Http\Controllers;

use App\Http\Requests\SiteVisitRequest;
use App\Models\Enquiry;
use App\Models\SiteVisit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SiteVisitController extends Controller
{
    public function index(Request $request): View
    {
        $siteVisits = SiteVisit::query()
            ->with(['enquiry.client', 'assignedTo'])
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->orderBy('scheduled_at')
            ->paginate(15)
            ->withQueryString();

        return view('site-visits.index', compact('siteVisits'));
    }

    public function create(Request $request): View
    {
        $enquiry = Enquiry::findOrFail($request->get('enquiry_id'));
        $salesUsers = User::permission('site_visits.view')->get();

        return view('site-visits.create', compact('enquiry', 'salesUsers'));
    }

    public function store(SiteVisitRequest $request): RedirectResponse
    {
        $siteVisit = SiteVisit::create($request->validated() + ['created_by' => $request->user()->id]);
        $siteVisit->enquiry->update(['status' => 'site_visit_scheduled']);

        return redirect()->route('enquiries.show', $siteVisit->enquiry)->with('success', 'Site visit scheduled.');
    }

    public function edit(SiteVisit $siteVisit): View
    {
        $enquiry = $siteVisit->enquiry;
        $salesUsers = User::permission('site_visits.view')->get();

        return view('site-visits.edit', compact('siteVisit', 'enquiry', 'salesUsers'));
    }

    public function update(SiteVisitRequest $request, SiteVisit $siteVisit): RedirectResponse
    {
        $siteVisit->update($request->validated());

        return redirect()->route('site-visits.index')->with('success', 'Site visit updated.');
    }

    public function complete(Request $request, SiteVisit $siteVisit): RedirectResponse
    {
        $data = $request->validate([
            'notes' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
        ]);

        $siteVisit->update($data + ['status' => 'completed', 'visited_at' => now()]);

        return back()->with('success', 'Site visit marked complete.');
    }
}
