<?php

namespace App\Http\Controllers;

use App\Models\CompanyRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompanyRecordController extends Controller
{
    public function index(): View
    {
        $records = CompanyRecord::orderBy('type')->orderByDesc('issued_date')->get()->groupBy('type');

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
        ]);

        CompanyRecord::create($data);

        return redirect()->route('company-records.index')->with('success', 'Company record saved.');
    }

    public function edit(CompanyRecord $companyRecord): View
    {
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
        ]);

        $companyRecord->update($data);

        return redirect()->route('company-records.index')->with('success', 'Company record updated.');
    }

    public function destroy(CompanyRecord $companyRecord): RedirectResponse
    {
        $companyRecord->delete();

        return back()->with('success', 'Company record removed.');
    }
}
