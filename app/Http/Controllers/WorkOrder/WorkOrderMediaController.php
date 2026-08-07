<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class WorkOrderMediaController extends Controller
{
    private const COLLECTIONS = ['before_images', 'during_images', 'completion_images', 'videos', 'documents'];

    public function store(Request $request, WorkOrder $workOrder): RedirectResponse
    {
        $data = $request->validate([
            'collection' => ['required', 'in:'.implode(',', self::COLLECTIONS)],
            'file' => ['required', 'file', 'max:51200'],
        ]);

        $workOrder->addMediaFromRequest('file')->toMediaCollection($data['collection']);

        return back()->with('success', 'File uploaded.');
    }

    public function destroy(WorkOrder $workOrder, Media $media): RedirectResponse
    {
        abort_unless($media->model_id === $workOrder->id, 404);

        $media->delete();

        return back()->with('success', 'Media removed.');
    }
}
