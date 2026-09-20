<?php

namespace App\Http\Controllers;

use App\Models\Quotation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class QuotationMediaController extends Controller
{
    public function store(Request $request, Quotation $quotation): RedirectResponse
    {
        $request->validate([
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx,xls,xlsx'],
        ]);

        try {
            foreach ($request->file('files', []) as $file) {
                $quotation->addMedia($file)->toMediaCollection('attachments');
            }
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
        }

        return back()->with('success', 'Attachment(s) uploaded.');
    }

    public function destroy(Quotation $quotation, Media $media): RedirectResponse
    {
        $this->authorizeAdminOnly();

        abort_unless((string) $media->model_id === (string) $quotation->id, 404);

        $media->delete();

        return back()->with('success', 'Attachment removed.');
    }
}
