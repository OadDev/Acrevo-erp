<?php

namespace App\Http\Controllers;

use App\Models\CompanyRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class CompanyRecordController extends Controller
{
    private const FILE_RULES = ['file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx'];

    public function index(): View
    {
        $records = CompanyRecord::with('media')->orderBy('type')->orderByDesc('issued_date')->get()->groupBy('type');

        return view('company-records.index', compact('records'));
    }

    public function create(): View
    {
        return view('company-records.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:gst,msme,insurance,license,patent'],
            'name' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:150'],
            'issued_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => self::FILE_RULES,
        ]);

        $record = CompanyRecord::create(collect($data)->except('files')->all());

        try {
            foreach ($request->file('files', []) as $file) {
                $record->addMedia($file)->toMediaCollection('files');
            }
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
        }

        return redirect()->route('company-records.index')->with('success', 'Company record saved.');
    }

    public function edit(CompanyRecord $companyRecord): View
    {
        $companyRecord->load('media');

        return view('company-records.edit', ['record' => $companyRecord]);
    }

    public function update(Request $request, CompanyRecord $companyRecord): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:gst,msme,insurance,license,patent'],
            'name' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:150'],
            'issued_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => self::FILE_RULES,
        ]);

        $companyRecord->update(collect($data)->except('files')->all());

        try {
            foreach ($request->file('files', []) as $file) {
                $companyRecord->addMedia($file)->toMediaCollection('files');
            }
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
        }

        return redirect()->route('company-records.index')->with('success', 'Company record updated.');
    }

    public function destroy(CompanyRecord $companyRecord): RedirectResponse
    {
        $companyRecord->delete();

        return back()->with('success', 'Company record removed.');
    }

    public function destroyMedia(CompanyRecord $companyRecord, Media $media): RedirectResponse
    {
        abort_unless((string) $media->model_id === (string) $companyRecord->id, 404);

        $media->delete();

        return back()->with('success', 'File removed.');
    }
}
