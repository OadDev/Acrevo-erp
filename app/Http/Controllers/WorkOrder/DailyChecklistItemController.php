<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\DailyChecklistItem;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;

class DailyChecklistItemController extends Controller
{
    public function markDone(Request $request, WorkOrder $workOrder, DailyChecklistItem $item): RedirectResponse
    {
        abort_unless($item->dailyChecklist->work_order_id === $workOrder->id, 404);
        abort_if($item->is_done, 422, 'This item is already marked done.');

        $request->validate([
            'proof' => ['required', 'file', 'max:51200', 'mimes:jpg,jpeg,png,mp4,mov,avi'],
        ]);

        try {
            $item->addMediaFromRequest('proof')->toMediaCollection('proof');
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['proof' => 'That file is too large (max 50MB).']);
        }

        $item->update(['is_done' => true, 'done_at' => now(), 'done_by' => $request->user()->id]);

        return back()->with('success', 'Marked done.');
    }

    public function destroy(WorkOrder $workOrder, DailyChecklistItem $item): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($item->dailyChecklist->work_order_id === $workOrder->id, 404);

        $item->delete();

        return back()->with('success', 'Checklist item removed.');
    }

    public function destroyProof(WorkOrder $workOrder, DailyChecklistItem $item): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($item->dailyChecklist->work_order_id === $workOrder->id, 404);

        $item->clearMediaCollection('proof');

        // "Done" is only meaningful backed by its proof photo - without one
        // the item goes back to pending rather than showing as done with
        // nothing to show for it.
        $item->update(['is_done' => false, 'done_at' => null, 'done_by' => null]);

        return back()->with('success', 'Proof removed.');
    }
}
