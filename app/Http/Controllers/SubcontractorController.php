<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\SiteSubContractor;
use App\Models\User;
use App\Models\VendorPayment;
use App\Models\WorkOrder;
use App\Models\WorkOrderSubContractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubcontractorController extends Controller
{
    public function index(Request $request): View
    {
        $subcontractors = User::role('Sub Contractor')
            ->with('subcontractorProfile')
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when($request->get('status') === 'verified', fn ($q) => $q->whereHas('subcontractorProfile', fn ($q2) => $q2->where('is_verified', true)))
            ->when($request->get('status') === 'unverified', fn ($q) => $q->whereDoesntHave('subcontractorProfile', fn ($q2) => $q2->where('is_verified', true)))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return view('subcontractors.index', compact('subcontractors'));
    }

    public function show(User $subcontractor): View
    {
        abort_unless($subcontractor->hasRole('Sub Contractor'), 404);

        $subcontractor->load('subcontractorProfile.verifiedBy');

        $assignedSites = SiteSubContractor::with('site.client')
            ->where('user_id', $subcontractor->id)
            ->whereNull('unassigned_at')
            ->latest('assigned_at')
            ->get();

        $assignedWorkOrders = WorkOrderSubContractor::with('workOrder.client')
            ->where('user_id', $subcontractor->id)
            ->whereNull('unassigned_at')
            ->latest('assigned_at')
            ->get();

        $payments = VendorPayment::where('user_id', $subcontractor->id)->latest('payment_date')->limit(20)->get();

        $sites = Site::where('status', 'active')->orderBy('site_no')->get();
        $workOrders = WorkOrder::orderByDesc('created_at')->limit(200)->get();

        return view('subcontractors.show', compact('subcontractor', 'assignedSites', 'assignedWorkOrders', 'payments', 'sites', 'workOrders'));
    }

    public function update(Request $request, User $subcontractor): RedirectResponse
    {
        abort_unless($subcontractor->hasRole('Sub Contractor'), 404);

        $data = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'pan_number' => ['nullable', 'string', 'max:20'],
            'bank_name' => ['nullable', 'string', 'max:150'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'bank_ifsc' => ['nullable', 'string', 'max:20'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $subcontractor->subcontractorProfile()->updateOrCreate(
            ['user_id' => $subcontractor->id],
            $data + ['created_by' => $subcontractor->subcontractorProfile?->created_by ?? $request->user()->id]
        );

        return redirect()->route('subcontractors.show', $subcontractor)->with('success', 'Subcontractor details saved.');
    }

    public function verify(Request $request, User $subcontractor): RedirectResponse
    {
        abort_unless($subcontractor->hasRole('Sub Contractor'), 404);

        $profile = $subcontractor->subcontractorProfile()->firstOrCreate(['user_id' => $subcontractor->id], ['created_by' => $request->user()->id]);
        $profile->update(['is_verified' => true, 'verified_at' => now(), 'verified_by' => $request->user()->id]);

        return back()->with('success', 'Subcontractor marked as Authorised Subcontractor.');
    }

    public function unverify(User $subcontractor): RedirectResponse
    {
        abort_unless($subcontractor->hasRole('Sub Contractor'), 404);

        $subcontractor->subcontractorProfile?->update(['is_verified' => false, 'verified_at' => null, 'verified_by' => null]);

        return back()->with('success', 'Authorised Subcontractor badge revoked.');
    }

    public function assignSite(Request $request, User $subcontractor): RedirectResponse
    {
        abort_unless($subcontractor->hasRole('Sub Contractor'), 404);

        $data = $request->validate(['site_id' => ['required', 'exists:sites,id']]);

        $alreadyAssigned = SiteSubContractor::where('site_id', $data['site_id'])
            ->where('user_id', $subcontractor->id)
            ->whereNull('unassigned_at')
            ->exists();

        abort_if($alreadyAssigned, 422, 'This site is already assigned to this subcontractor.');

        SiteSubContractor::create([
            'site_id' => $data['site_id'],
            'user_id' => $subcontractor->id,
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        return back()->with('success', 'Site assigned.');
    }

    public function unassignSite(User $subcontractor, SiteSubContractor $assignment): RedirectResponse
    {
        abort_unless($assignment->user_id === $subcontractor->id, 404);

        $assignment->update(['unassigned_at' => now()]);

        return back()->with('success', 'Site unassigned.');
    }

    public function assignWorkOrder(Request $request, User $subcontractor): RedirectResponse
    {
        abort_unless($subcontractor->hasRole('Sub Contractor'), 404);

        $data = $request->validate(['work_order_id' => ['required', 'exists:work_orders,id']]);

        $alreadyAssigned = WorkOrderSubContractor::where('work_order_id', $data['work_order_id'])
            ->where('user_id', $subcontractor->id)
            ->whereNull('unassigned_at')
            ->exists();

        abort_if($alreadyAssigned, 422, 'This work order is already assigned to this subcontractor.');

        $workOrder = WorkOrder::findOrFail($data['work_order_id']);

        WorkOrderSubContractor::create([
            'work_order_id' => $workOrder->id,
            'user_id' => $subcontractor->id,
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        if ($workOrder->status === 'pending_hr_assignment') {
            $workOrder->transitionTo('team_assigned', 'Sub-contractor assigned.');
        }

        return back()->with('success', 'Work order assigned.');
    }

    public function unassignWorkOrder(User $subcontractor, WorkOrderSubContractor $assignment): RedirectResponse
    {
        abort_unless($assignment->user_id === $subcontractor->id, 404);

        $assignment->update(['unassigned_at' => now()]);

        return back()->with('success', 'Work order unassigned.');
    }
}
