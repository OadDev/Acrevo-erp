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
            'items' => ['required', 'string'],
        ]);

        $items = collect(explode("\n", $data['items']))
            ->map(fn ($line) => trim($line))
            ->filter()
            ->values()
            ->all();

        $workOrder->dailyChecklists()->updateOrCreate(
            ['executive_team_id' => $data['executive_team_id'], 'date' => now()->toDateString()],
            ['items' => $items, 'created_by' => $request->user()->id]
        );

        if ($workOrder->status === 'team_assigned') {
            $workOrder->transitionTo('in_progress', 'First daily checklist submitted.');
        }

        return back()->with('success', 'Checklist saved.');
    }
}
