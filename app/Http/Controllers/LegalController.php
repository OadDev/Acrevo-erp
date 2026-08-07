<?php

namespace App\Http\Controllers;

use App\Models\LegalDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalController extends Controller
{
    public function index(): View
    {
        $documents = LegalDocument::with(['client', 'workOrder'])->latest()->paginate(15);

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
        ]);

        LegalDocument::create($data + ['status' => 'active', 'created_by' => $request->user()->id]);

        return back()->with('success', 'Legal document recorded.');
    }
}
