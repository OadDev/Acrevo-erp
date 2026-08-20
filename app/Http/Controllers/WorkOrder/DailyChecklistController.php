<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\DailyChecklist;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DailyChecklistController extends Controller
{
    public function index(WorkOrder $workOrder): RedirectResponse
    {
        return redirect()->route('work-orders.show', $workOrder);
    }

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'executive_team_id' => [$workOrder->execution_way === 'way_2' ? 'nullable' : 'required', 'exists:executive_teams,id'],
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'items' => ['required', 'string'],
        ]);

        $items = collect(explode("\n", $data['items']))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values();

        $checklist = $workOrder->dailyChecklists()->create([
            'executive_team_id' => $data['executive_team_id'] ?? null,
            'date' => $data['date'],
            'title' => $data['title'],
            'created_by' => $request->user()->id,
        ]);

        $items->each(fn ($description, $index) => $checklist->checklistItems()->create([
            'description' => $description,
            'sort_order' => $index,
        ]));

        if ($workOrder->status === 'team_assigned') {
            $workOrder->transitionTo('in_progress', 'First daily work entry submitted.');
        }

        return back()->with('success', 'Daily work entry saved.');
    }

    public function update(Request $request, WorkOrder $workOrder, DailyChecklist $checklist): RedirectResponse
    {
        $this->authorizeAdminOnly();

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'executive_team_id' => [$workOrder->execution_way === 'way_2' ? 'nullable' : 'required', 'exists:executive_teams,id'],
            'date' => ['required', 'date'],
        ]);

        $checklist->update($data);

        return back()->with('success', 'Daily work entry updated.');
    }

    public function destroy(WorkOrder $workOrder, DailyChecklist $checklist): RedirectResponse
    {
        $this->authorizeAdminOnly();

        $checklist->checklistItems()->delete();
        $checklist->delete();

        return back()->with('success', 'Daily work entry removed.');
    }
}
