<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class LegalController extends Controller
{
    private const FILE_RULES = ['file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx'];

    public function index(): View
    {
        $documents = LegalDocument::with(['client', 'workOrder', 'media'])->latest()->paginate(15);

        return view('legal.index', compact('documents'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'type' => ['required', 'in:agreement,contract,notice,other'],
            'title' => ['required', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'issued_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => self::FILE_RULES,
        ]);

        $document = LegalDocument::create($data + ['status' => 'active', 'created_by' => $request->user()->id]);

        try {
            foreach ($request->file('files', []) as $file) {
                $document->addMedia($file)->toMediaCollection('files');
            }
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
        }

        return back()->with('success', 'Legal document recorded.');
    }

    public function edit(LegalDocument $legalDocument): View
    {
        $legalDocument->load('media');

        return view('legal.edit', ['document' => $legalDocument]);
    }

    public function update(Request $request, LegalDocument $legalDocument): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:agreement,contract,notice,other'],
            'title' => ['required', 'string', 'max:255'],
            'reference_no' => ['nullable', 'string', 'max:100'],
            'issued_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'status' => ['required', 'in:active,expired,terminated'],
            'notes' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => self::FILE_RULES,
        ]);

        $legalDocument->update(collect($data)->except('files')->all());

        try {
            foreach ($request->file('files', []) as $file) {
                $legalDocument->addMedia($file)->toMediaCollection('files');
            }
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
        }

        return redirect()->route('legal.index')->with('success', 'Legal document updated.');
    }

    public function destroy(LegalDocument $legalDocument): RedirectResponse
    {
        $legalDocument->delete();

        return redirect()->route('legal.index')->with('success', 'Legal document removed.');
    }

    public function destroyMedia(LegalDocument $legalDocument, Media $media): RedirectResponse
    {
        abort_unless((string) $media->model_id === (string) $legalDocument->id, 404);

        $media->delete();

        return back()->with('success', 'File removed.');
    }
}
