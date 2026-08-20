<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsWorkOrderPdfRelations;
use App\Http\Requests\WorkOrderRequest;
use App\Models\Employee;
use App\Models\ExecutiveTeam;
use App\Models\Quotation;
use App\Models\Site;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderExecutiveTeam;
use App\Models\WorkOrderSubContractor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WorkOrderController extends Controller
{
    use LoadsWorkOrderPdfRelations;

    public function index(Request $request): View
    {
        $workOrders = WorkOrder::query()
            ->with(['client', 'site', 'executiveTeams.executiveTeam'])
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('title', 'like', "%{$search}%")
                ->orWhere('work_order_no', 'like', "%{$search}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('work-orders.index', compact('workOrders'));
    }

    public function completed(): View
    {
        $workOrders = WorkOrder::query()
            ->with(['client', 'site'])
            ->whereIn('status', ['completed', 'cancelled'])
            ->latest()
            ->paginate(15);

        return view('work-orders.completed', compact('workOrders'));
    }

    public function create(Request $request): View
    {
        $quotation = Quotation::with('client', 'site')->find($request->get('quotation_id'));

        abort_unless($quotation && $quotation->status === 'approved', 404, 'A work order can only be generated from an approved quotation.');

        $site = $quotation->site ?? Site::create([
            'quotation_id' => $quotation->id,
            'client_id' => $quotation->client_id,
            'address' => $quotation->client->address,
            'city' => $quotation->client->city,
            'state' => $quotation->client->state,
            'pincode' => $quotation->client->pincode,
            'site_contact_name' => $quotation->client->name,
            'site_contact_phone' => $quotation->client->phone,
            'created_by' => $request->user()->id,
        ]);

        abort_if($site->status === 'completed', 422, 'This site was already marked completed and handed over. Ask an Admin to reopen it before adding more work orders.');

        $teamLeaders = User::role('Executive Team Leader')->orderBy('name')->get();

        return view('work-orders.create', compact('quotation', 'site', 'teamLeaders'));
    }

    public function store(WorkOrderRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $quotation = Quotation::find($data['quotation_id']);
        $site = Site::findOrFail($data['site_id']);
        abort_if($site->status === 'completed', 422, 'This site was already marked completed and handed over. Ask an Admin to reopen it before adding more work orders.');

        $team = null;
        if (! empty($data['team_leader_id'])) {
            $team = ExecutiveTeam::where('team_leader_id', $data['team_leader_id'])->where('is_active', true)->first();

            if (! $team) {
                return back()->withInput()->withErrors(['team_leader_id' => 'This Team Leader doesn\'t have an active Executive Team yet. Ask HR to form one first, or assign the team later from the work order\'s Team tab.']);
            }
        }

        Site::where('id', $data['site_id'])->update([
            'address' => $data['site_address'] ?? null,
            'city' => $data['site_city'] ?? null,
            'state' => $data['site_state'] ?? null,
            'pincode' => $data['site_pincode'] ?? null,
            'construction_site_location' => $data['construction_site_location'] ?? null,
            'client_living_location' => $data['client_living_location'] ?? null,
            'site_contact_name' => $data['site_contact_name'] ?? null,
            'site_contact_phone' => $data['site_contact_phone'] ?? null,
        ]);

        $materialRows = collect($data['materials'] ?? [])
            ->filter(fn ($row) => filled($row['material_name'] ?? null) && filled($row['quantity'] ?? null) && isset($row['rate']));
        $labourRows = collect($data['labour'] ?? [])
            ->filter(fn ($row) => filled($row['labour_type'] ?? null) && filled($row['count'] ?? null) && isset($row['wage_rate']));
        $equipmentRows = collect($data['equipment'] ?? [])
            ->filter(fn ($row) => filled($row['item_name'] ?? null) && filled($row['quantity'] ?? null) && isset($row['rate']));
        $transportRows = collect($data['transport'] ?? [])
            ->filter(fn ($row) => filled($row['item_name'] ?? null) && filled($row['quantity'] ?? null) && isset($row['rate']));
        $miscRows = collect($data['misc'] ?? [])
            ->filter(fn ($row) => filled($row['item_name'] ?? null) && filled($row['quantity'] ?? null) && isset($row['rate']));

        $materialBudget = $materialRows->isNotEmpty()
            ? $materialRows->sum(fn ($row) => $row['quantity'] * $row['rate'])
            : ($data['estimated_material_budget'] ?? 0);
        $labourBudget = $labourRows->isNotEmpty()
            ? $labourRows->sum(fn ($row) => $row['count'] * $row['wage_rate'])
            : ($data['estimated_labour_budget'] ?? 0);
        $equipmentBudget = $equipmentRows->isNotEmpty()
            ? $equipmentRows->sum(fn ($row) => $row['quantity'] * $row['rate'])
            : ($data['estimated_equipment_budget'] ?? 0);
        $transportBudget = $transportRows->isNotEmpty()
            ? $transportRows->sum(fn ($row) => $row['quantity'] * $row['rate'])
            : ($data['estimated_transport_budget'] ?? 0);
        $miscBudget = $miscRows->isNotEmpty()
            ? $miscRows->sum(fn ($row) => $row['quantity'] * $row['rate'])
            : ($data['estimated_misc_budget'] ?? 0);

        $totalBudget = $materialBudget + $labourBudget + $equipmentBudget + $transportBudget + $miscBudget;

        $workOrder = WorkOrder::create([
            'quotation_id' => $data['quotation_id'],
            'site_id' => $data['site_id'],
            'client_id' => $data['client_id'],
            'title' => $data['title'],
            'scope' => $data['scope'] ?? null,
            'execution_way' => $data['execution_way'],
            'priority' => $data['priority'],
            'start_date' => $data['start_date'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'estimated_material_budget' => $materialBudget ?: null,
            'estimated_labour_budget' => $labourBudget ?: null,
            'estimated_equipment_budget' => $equipmentBudget ?: null,
            'estimated_transport_budget' => $transportBudget ?: null,
            'estimated_misc_budget' => $miscBudget ?: null,
            'budget_amount' => $totalBudget ?: null,
            'enquiry_id' => $quotation?->enquiry_id,
            'type' => 'new',
            'status' => 'pending_hr_assignment',
            'created_by' => $request->user()->id,
        ]);

        $this->saveBudgetItems($workOrder, 'material', $materialRows, fn ($row) => [
            'name' => $row['material_name'], 'brand' => $row['brand'] ?? null, 'size' => $row['size'] ?? null,
            'unit' => $row['unit'] ?? null, 'quantity' => $row['quantity'], 'rate' => $row['rate'], 'vendor' => $row['vendor'] ?? null,
            'amount' => $row['quantity'] * $row['rate'],
        ]);
        $this->saveBudgetItems($workOrder, 'labour', $labourRows, fn ($row) => [
            'name' => $row['labour_type'], 'quantity' => $row['count'], 'hours' => $row['hours'] ?? null, 'rate' => $row['wage_rate'],
            'amount' => $row['count'] * $row['wage_rate'],
        ]);
        $this->saveBudgetItems($workOrder, 'equipment', $equipmentRows, fn ($row) => [
            'name' => $row['item_name'], 'unit' => $row['unit'] ?? null, 'quantity' => $row['quantity'], 'rate' => $row['rate'],
            'vendor' => $row['vendor'] ?? null, 'amount' => $row['quantity'] * $row['rate'],
        ]);
        $this->saveBudgetItems($workOrder, 'transport', $transportRows, fn ($row) => [
            'name' => $row['item_name'], 'unit' => $row['unit'] ?? null, 'quantity' => $row['quantity'], 'rate' => $row['rate'],
            'vendor' => $row['vendor'] ?? null, 'amount' => $row['quantity'] * $row['rate'],
        ]);
        $this->saveBudgetItems($workOrder, 'misc', $miscRows, fn ($row) => [
            'name' => $row['item_name'], 'unit' => $row['unit'] ?? null, 'quantity' => $row['quantity'], 'rate' => $row['rate'],
            'vendor' => $row['vendor'] ?? null, 'amount' => $row['quantity'] * $row['rate'],
        ]);

        $this->saveTimeSchedules($workOrder, $data['time_schedules'] ?? []);
        $this->saveProcedures($workOrder, $data['procedures'] ?? [], $data['start_date'] ?? null, $request->user()->id);

        if ($team) {
            WorkOrderExecutiveTeam::create([
                'work_order_id' => $workOrder->id,
                'executive_team_id' => $team->id,
                'assigned_by' => $request->user()->id,
                'assigned_at' => now(),
            ]);

            $workOrder->transitionTo('team_assigned', 'Executive team assigned at work order creation.');
        }

        $quotation?->enquiry?->update(['status' => 'converted']);

        return redirect()->route('work-orders.show', $workOrder)->with('success', 'Work order generated successfully.');
    }

    public function edit(WorkOrder $workOrder): View
    {
        $this->authorizeAdminOnly();

        $byCategory = fn ($category) => $workOrder->budgetItems()->where('category', $category)->get();

        $materials = $byCategory('material')->map(fn ($i) => [
            'material_name' => $i->name, 'brand' => $i->brand, 'size' => $i->size,
            'unit' => $i->unit ?? 'Nos', 'quantity' => $i->quantity, 'rate' => $i->rate, 'vendor' => $i->vendor,
        ])->all() ?: [['material_name' => '', 'brand' => '', 'size' => '', 'unit' => 'Nos', 'quantity' => '', 'rate' => '', 'vendor' => '']];

        $labour = $byCategory('labour')->map(fn ($i) => [
            'labour_type' => $i->name, 'count' => $i->quantity, 'hours' => $i->hours, 'wage_rate' => $i->rate,
        ])->all() ?: [['labour_type' => '', 'count' => 1, 'hours' => '', 'wage_rate' => '']];

        $equipment = $byCategory('equipment')->map(fn ($i) => [
            'item_name' => $i->name, 'unit' => $i->unit ?? 'Nos', 'quantity' => $i->quantity, 'rate' => $i->rate, 'vendor' => $i->vendor,
        ])->all() ?: [['item_name' => '', 'unit' => 'Nos', 'quantity' => '', 'rate' => '', 'vendor' => '']];

        $transport = $byCategory('transport')->map(fn ($i) => [
            'item_name' => $i->name, 'unit' => $i->unit ?? 'Trip', 'quantity' => $i->quantity, 'rate' => $i->rate, 'vendor' => $i->vendor,
        ])->all() ?: [['item_name' => '', 'unit' => 'Trip', 'quantity' => '', 'rate' => '', 'vendor' => '']];

        $misc = $byCategory('misc')->map(fn ($i) => [
            'item_name' => $i->name, 'unit' => $i->unit ?? 'Nos', 'quantity' => $i->quantity, 'rate' => $i->rate, 'vendor' => $i->vendor,
        ])->all() ?: [['item_name' => '', 'unit' => 'Nos', 'quantity' => '', 'rate' => '', 'vendor' => '']];

        $scheduleItems = $workOrder->measurementBooks()->where('type', 'schedule')->first()?->items ?? collect();
        $procedures = $scheduleItems->map(fn ($i) => [
            'item_description' => $i->item_description, 'length' => $i->length, 'breadth' => $i->breadth,
            'height' => $i->height, 'quantity' => $i->quantity, 'unit' => $i->unit ?? 'Sqft',
        ])->all() ?: [['item_description' => '', 'length' => '', 'breadth' => '', 'height' => '', 'quantity' => '', 'unit' => 'Sqft']];

        $timeSchedules = $workOrder->timeSchedules->map(fn ($t) => [
            'time_to_finish' => $t->time_to_finish, 'unit' => $t->unit, 'remark' => $t->remark,
        ])->all() ?: [['time_to_finish' => '', 'unit' => '', 'remark' => '']];

        return view('work-orders.edit', compact('workOrder', 'materials', 'labour', 'equipment', 'transport', 'misc', 'procedures', 'timeSchedules'));
    }

    public function update(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorizeAdminOnly();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string'],
            'priority' => ['required', 'in:low,medium,high,urgent'],
            'start_date' => ['nullable', 'date'],
            'deadline' => ['nullable', 'date', 'after_or_equal:start_date'],
            'materials' => ['nullable', 'array'],
            'materials.*.material_name' => ['nullable', 'string', 'max:255'],
            'materials.*.brand' => ['nullable', 'string', 'max:150'],
            'materials.*.size' => ['nullable', 'string', 'max:100'],
            'materials.*.unit' => ['nullable', 'string', 'max:30'],
            'materials.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'materials.*.rate' => ['nullable', 'numeric', 'min:0'],
            'materials.*.vendor' => ['nullable', 'string', 'max:255'],
            'labour' => ['nullable', 'array'],
            'labour.*.labour_type' => ['nullable', 'string', 'max:150'],
            'labour.*.count' => ['nullable', 'integer', 'min:1'],
            'labour.*.hours' => ['nullable', 'numeric', 'min:0'],
            'labour.*.wage_rate' => ['nullable', 'numeric', 'min:0'],
            'equipment' => ['nullable', 'array'],
            'equipment.*.item_name' => ['nullable', 'string', 'max:255'],
            'equipment.*.unit' => ['nullable', 'string', 'max:30'],
            'equipment.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'equipment.*.rate' => ['nullable', 'numeric', 'min:0'],
            'equipment.*.vendor' => ['nullable', 'string', 'max:255'],
            'transport' => ['nullable', 'array'],
            'transport.*.item_name' => ['nullable', 'string', 'max:255'],
            'transport.*.unit' => ['nullable', 'string', 'max:30'],
            'transport.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'transport.*.rate' => ['nullable', 'numeric', 'min:0'],
            'transport.*.vendor' => ['nullable', 'string', 'max:255'],
            'misc' => ['nullable', 'array'],
            'misc.*.item_name' => ['nullable', 'string', 'max:255'],
            'misc.*.unit' => ['nullable', 'string', 'max:30'],
            'misc.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'misc.*.rate' => ['nullable', 'numeric', 'min:0'],
            'misc.*.vendor' => ['nullable', 'string', 'max:255'],
            'time_schedules' => ['nullable', 'array'],
            'time_schedules.*.time_to_finish' => ['nullable', 'string', 'max:100'],
            'time_schedules.*.unit' => ['nullable', 'string', 'max:50'],
            'time_schedules.*.remark' => ['nullable', 'string', 'max:255'],
            'procedures' => ['nullable', 'array'],
            'procedures.*.item_description' => ['nullable', 'string', 'max:255'],
            'procedures.*.length' => ['nullable', 'numeric', 'min:0'],
            'procedures.*.breadth' => ['nullable', 'numeric', 'min:0'],
            'procedures.*.height' => ['nullable', 'numeric', 'min:0'],
            'procedures.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'procedures.*.unit' => ['nullable', 'string', 'max:30'],
        ]);

        $materialRows = collect($data['materials'] ?? [])
            ->filter(fn ($row) => filled($row['material_name'] ?? null) && filled($row['quantity'] ?? null) && isset($row['rate']));
        $labourRows = collect($data['labour'] ?? [])
            ->filter(fn ($row) => filled($row['labour_type'] ?? null) && filled($row['count'] ?? null) && isset($row['wage_rate']));
        $equipmentRows = collect($data['equipment'] ?? [])
            ->filter(fn ($row) => filled($row['item_name'] ?? null) && filled($row['quantity'] ?? null) && isset($row['rate']));
        $transportRows = collect($data['transport'] ?? [])
            ->filter(fn ($row) => filled($row['item_name'] ?? null) && filled($row['quantity'] ?? null) && isset($row['rate']));
        $miscRows = collect($data['misc'] ?? [])
            ->filter(fn ($row) => filled($row['item_name'] ?? null) && filled($row['quantity'] ?? null) && isset($row['rate']));

        $materialBudget = $materialRows->sum(fn ($row) => $row['quantity'] * $row['rate']);
        $labourBudget = $labourRows->sum(fn ($row) => $row['count'] * $row['wage_rate']);
        $equipmentBudget = $equipmentRows->sum(fn ($row) => $row['quantity'] * $row['rate']);
        $transportBudget = $transportRows->sum(fn ($row) => $row['quantity'] * $row['rate']);
        $miscBudget = $miscRows->sum(fn ($row) => $row['quantity'] * $row['rate']);
        $totalBudget = $materialBudget + $labourBudget + $equipmentBudget + $transportBudget + $miscBudget;

        $workOrder->update([
            'title' => $data['title'],
            'scope' => $data['scope'] ?? null,
            'priority' => $data['priority'],
            'start_date' => $data['start_date'] ?? null,
            'deadline' => $data['deadline'] ?? null,
            'estimated_material_budget' => $materialBudget ?: null,
            'estimated_labour_budget' => $labourBudget ?: null,
            'estimated_equipment_budget' => $equipmentBudget ?: null,
            'estimated_transport_budget' => $transportBudget ?: null,
            'estimated_misc_budget' => $miscBudget ?: null,
            'budget_amount' => $totalBudget ?: null,
        ]);

        $this->saveBudgetItems($workOrder, 'material', $materialRows, fn ($row) => [
            'name' => $row['material_name'], 'brand' => $row['brand'] ?? null, 'size' => $row['size'] ?? null,
            'unit' => $row['unit'] ?? null, 'quantity' => $row['quantity'], 'rate' => $row['rate'], 'vendor' => $row['vendor'] ?? null,
            'amount' => $row['quantity'] * $row['rate'],
        ]);
        $this->saveBudgetItems($workOrder, 'labour', $labourRows, fn ($row) => [
            'name' => $row['labour_type'], 'quantity' => $row['count'], 'hours' => $row['hours'] ?? null, 'rate' => $row['wage_rate'],
            'amount' => $row['count'] * $row['wage_rate'],
        ]);
        $this->saveBudgetItems($workOrder, 'equipment', $equipmentRows, fn ($row) => [
            'name' => $row['item_name'], 'unit' => $row['unit'] ?? null, 'quantity' => $row['quantity'], 'rate' => $row['rate'],
            'vendor' => $row['vendor'] ?? null, 'amount' => $row['quantity'] * $row['rate'],
        ]);
        $this->saveBudgetItems($workOrder, 'transport', $transportRows, fn ($row) => [
            'name' => $row['item_name'], 'unit' => $row['unit'] ?? null, 'quantity' => $row['quantity'], 'rate' => $row['rate'],
            'vendor' => $row['vendor'] ?? null, 'amount' => $row['quantity'] * $row['rate'],
        ]);
        $this->saveBudgetItems($workOrder, 'misc', $miscRows, fn ($row) => [
            'name' => $row['item_name'], 'unit' => $row['unit'] ?? null, 'quantity' => $row['quantity'], 'rate' => $row['rate'],
            'vendor' => $row['vendor'] ?? null, 'amount' => $row['quantity'] * $row['rate'],
        ]);

        $this->saveTimeSchedules($workOrder, $data['time_schedules'] ?? []);
        $this->saveProcedures($workOrder, $data['procedures'] ?? [], $data['start_date'] ?? null, $request->user()->id);

        return redirect()->route('work-orders.show', $workOrder)->with('success', 'Work order updated.');
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array<string, mixed>>  $rows
     */
    private function saveBudgetItems(WorkOrder $workOrder, string $category, \Illuminate\Support\Collection $rows, \Closure $map): void
    {
        $workOrder->budgetItems()->where('category', $category)->delete();

        foreach ($rows as $row) {
            $workOrder->budgetItems()->create(['category' => $category] + $map($row));
        }
    }

    private function saveTimeSchedules(WorkOrder $workOrder, array $rows): void
    {
        $rows = collect($rows)->filter(fn ($row) => filled($row['time_to_finish'] ?? null));

        $workOrder->timeSchedules()->delete();

        foreach ($rows as $row) {
            $workOrder->timeSchedules()->create([
                'time_to_finish' => $row['time_to_finish'],
                'unit' => $row['unit'] ?? null,
                'remark' => $row['remark'] ?? null,
            ]);
        }
    }

    private function saveProcedures(WorkOrder $workOrder, array $rows, ?string $startDate, string $userId): void
    {
        $rows = collect($rows)->filter(fn ($row) => filled($row['item_description'] ?? null));

        $scheduleBook = $workOrder->measurementBooks()->where('type', 'schedule')->first();

        if ($rows->isEmpty()) {
            $scheduleBook?->items()->delete();

            return;
        }

        $scheduleBook ??= $workOrder->measurementBooks()->create([
            'type' => 'schedule',
            'description' => 'Work Schedule (M.Book)',
            'date' => $startDate ?? now()->toDateString(),
            'recorded_by' => $userId,
            'status' => 'draft',
        ]);

        $scheduleBook->items()->delete();

        foreach ($rows as $row) {
            $scheduleBook->items()->create([
                'item_description' => $row['item_description'],
                'unit' => $row['unit'] ?: 'Sqft',
                'length' => $row['length'] ?? null,
                'breadth' => $row['breadth'] ?? null,
                'height' => $row['height'] ?? null,
                'quantity' => $row['quantity'] ?? 0,
                'rate' => 0,
                'amount' => 0,
            ]);
        }
    }

    public function destroy(WorkOrder $workOrder): RedirectResponse
    {
        $this->authorizeAdminOnly();

        // Otherwise a still-"active" (unassigned_at null) assignment to a
        // work order that no longer exists is a dead end: it blocks the
        // Executive Team from ever being removed, with no page left to
        // unassign it from since the work order itself is gone.
        $workOrder->executiveTeams()->whereNull('unassigned_at')->update(['unassigned_at' => now()]);
        $workOrder->subContractors()->whereNull('unassigned_at')->update(['unassigned_at' => now()]);

        $workOrder->delete();

        return redirect()->route('work-orders.index')->with('success', 'Work order removed.');
    }

    public function show(WorkOrder $workOrder): View
    {
        $this->authorize('view', $workOrder);

        $this->loadWorkOrderPdfRelations($workOrder);

        $availableTeams = ExecutiveTeam::where('is_active', true)->get();
        $activeEmployees = Employee::where('status', 'active')->orderBy('name')->get();
        $subContractorUsers = User::role('Sub Contractor')->where('is_active', true)->orderBy('name')->get();

        return view('work-orders.show', compact('workOrder', 'availableTeams', 'activeEmployees', 'subContractorUsers'));
    }

    public function cancel(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('cancel', $workOrder);

        $data = $request->validate(['remarks' => ['nullable', 'string']]);
        $workOrder->transitionTo('cancelled', $data['remarks'] ?? 'Work order cancelled.');

        return back()->with('success', 'Work order cancelled.');
    }

    public function submitForQc(WorkOrder $workOrder): RedirectResponse
    {
        abort_unless(in_array($workOrder->status, ['in_progress', 'rework_in_progress']), 422, 'This work order is not in a state that can be submitted for QC.');

        $workOrder->transitionTo('qc_pending', 'Work marked complete by the executive team — awaiting QC.');

        return back()->with('success', 'Submitted for QC.');
    }

    public function updateSite(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        abort_unless($workOrder->site, 404);

        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'site_contact_name' => ['nullable', 'string', 'max:255'],
            'site_contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $workOrder->site->update($data);

        return back()->with('success', 'Site details updated.');
    }

    public function assignTeam(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate(['executive_team_id' => ['required', 'exists:executive_teams,id']]);

        WorkOrderExecutiveTeam::create([
            'work_order_id' => $workOrder->id,
            'executive_team_id' => $data['executive_team_id'],
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        if ($workOrder->status === 'pending_hr_assignment') {
            $workOrder->transitionTo('team_assigned', 'Executive team assigned by HR.');
        }

        return back()->with('success', 'Executive team assigned.');
    }

    public function unassignTeam(WorkOrder $workOrder, WorkOrderExecutiveTeam $assignment): RedirectResponse
    {
        $assignment->update(['unassigned_at' => now()]);

        return back()->with('success', 'Executive team unassigned.');
    }

    public function assignSubContractor(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate(['sub_contractor_user_id' => ['required', 'exists:users,id']]);

        $subContractor = User::findOrFail($data['sub_contractor_user_id']);
        abort_unless($subContractor->hasRole('Sub Contractor'), 422, 'Selected user is not a Sub Contractor.');

        WorkOrderSubContractor::create([
            'work_order_id' => $workOrder->id,
            'user_id' => $subContractor->id,
            'assigned_by' => $request->user()->id,
            'assigned_at' => now(),
        ]);

        if ($workOrder->status === 'pending_hr_assignment') {
            $workOrder->transitionTo('team_assigned', 'Sub-contractor assigned.');
        }

        return back()->with('success', 'Sub-contractor assigned.');
    }

    public function unassignSubContractor(WorkOrder $workOrder, WorkOrderSubContractor $assignment): RedirectResponse
    {
        $assignment->update(['unassigned_at' => now()]);

        return back()->with('success', 'Sub-contractor unassigned.');
    }

    public function createRework(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
        ]);

        $rework = WorkOrder::create($data + [
            'client_id' => $workOrder->client_id,
            'quotation_id' => $workOrder->quotation_id,
            'site_id' => $workOrder->site_id,
            'execution_way' => $workOrder->execution_way,
            'parent_work_order_id' => $workOrder->id,
            'type' => 'rework',
            'priority' => 'high',
            'status' => 'pending_hr_assignment',
            'created_by' => $request->user()->id,
        ]);

        $workOrder->transitionTo('rework_in_progress', 'Re-work order created: '.$rework->work_order_no);

        return redirect()->route('work-orders.show', $rework)->with('success', 'Re-work order created.');
    }

    public function createNext(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string'],
            'deadline' => ['nullable', 'date'],
        ]);

        $next = WorkOrder::create($data + [
            'client_id' => $workOrder->client_id,
            'quotation_id' => $workOrder->quotation_id,
            'site_id' => $workOrder->site_id,
            'execution_way' => $workOrder->execution_way,
            'parent_work_order_id' => $workOrder->id,
            'type' => 'next',
            'priority' => 'medium',
            'status' => 'pending_hr_assignment',
            'created_by' => $request->user()->id,
        ]);

        return redirect()->route('work-orders.show', $next)->with('success', 'Next work order created.');
    }

    public function complete(WorkOrder $workOrder): RedirectResponse
    {
        $workOrder->transitionTo('completed', 'Work order marked complete after final QC and client confirmation.');

        $workOrder->completionCertificates()->create([
            'issued_date' => now(),
            'issued_by' => auth()->id(),
        ]);

        return back()->with('success', 'Work order marked as completed.');
    }

    public function storeFeedback(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $this->authorize('view', $workOrder);

        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comments' => ['nullable', 'string'],
        ]);

        $workOrder->clientReviews()->create($data + [
            'client_id' => $workOrder->client_id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', 'Client feedback recorded.');
    }
}
