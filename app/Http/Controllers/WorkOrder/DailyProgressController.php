<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\DailyProgressReport;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class DailyProgressController extends Controller
{
    public function index(WorkOrder $workOrder): RedirectResponse
    {
        return redirect()->route('work-orders.show', $workOrder);
    }

    // Files submitted alongside a progress report belong to that specific
    // report, not the work order's general Media Gallery - so they're
    // stored on the report's own media collection, not WorkOrderMedia.
    private function attachFiles(DailyProgressReport $report, Request $request): ?string
    {
        try {
            foreach ($request->file('files', []) as $file) {
                $report->addMedia($file)->toMediaCollection('attachments');
            }
        } catch (FileIsTooBig $e) {
            return 'One of those files is too large (max 20MB).';
        }

        return null;
    }

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'executive_team_id' => [$workOrder->execution_way === 'way_2' ? 'nullable' : 'required', 'exists:executive_teams,id'],
            'date' => ['required', 'date'],
            'completed_work' => ['required', 'string'],
            'pending_work' => ['nullable', 'string'],
            'problems' => ['nullable', 'string'],
            'materials_required' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480'],
        ]);

        $report = $workOrder->dailyProgressReports()->create(collect($data)->except('files')->all() + [
            'submitted_by' => $request->user()->id,
        ]);

        if ($error = $this->attachFiles($report, $request)) {
            return back()->withErrors(['files' => $error]);
        }

        return back()->with('success', 'Progress report submitted.');
    }

    public function update(Request $request, WorkOrder $workOrder, DailyProgressReport $report): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($report->work_order_id === $workOrder->id, 404);

        $data = $request->validate([
            'executive_team_id' => [$workOrder->execution_way === 'way_2' ? 'nullable' : 'required', 'exists:executive_teams,id'],
            'date' => ['required', 'date'],
            'completed_work' => ['required', 'string'],
            'pending_work' => ['nullable', 'string'],
            'problems' => ['nullable', 'string'],
            'materials_required' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480'],
        ]);

        $report->update(collect($data)->except('files')->all());

        if ($error = $this->attachFiles($report, $request)) {
            return back()->withErrors(['files' => $error]);
        }

        return back()->with('success', 'Progress report updated.');
    }

    public function destroy(WorkOrder $workOrder, DailyProgressReport $report): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($report->work_order_id === $workOrder->id, 404);

        $report->delete();

        return back()->with('success', 'Progress report removed.');
    }

    public function destroyMedia(WorkOrder $workOrder, DailyProgressReport $report, Media $media): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($report->work_order_id === $workOrder->id, 404);
        abort_unless((string) $media->model_id === (string) $report->id, 404);

        $media->delete();

        return back()->with('success', 'File removed.');
    }
}
