<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
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
            'executive_team_id' => ['required', 'exists:executive_teams,id'],
            'title' => ['required', 'string', 'max:255'],
            'items' => ['required', 'string'],
        ]);

        $items = collect(explode("\n", $data['items']))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values();

        $checklist = $workOrder->dailyChecklists()->create([
            'executive_team_id' => $data['executive_team_id'],
            'date' => now()->toDateString(),
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
}
