<?php

namespace App\Http\Controllers;

use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class SiteDocumentController extends Controller
{
    public function store(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', 'in:'.implode(',', Site::DOCUMENT_CATEGORIES)],
            'file' => ['required', 'file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx'],
        ]);

        try {
            $site->addMediaFromRequest('file')->toMediaCollection($data['category']);
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['file' => 'That file is too large (max 20MB).']);
        }

        return back()->with('success', 'Document uploaded.');
    }

    public function destroy(Site $site, Media $media): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless($media->model_id === $site->id, 404);

        $media->delete();

        return back()->with('success', 'Document removed.');
    }
}
