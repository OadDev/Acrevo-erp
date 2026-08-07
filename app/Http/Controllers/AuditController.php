<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuditController extends Controller
{
    public function index(): View
    {
        $audits = Audit::with(['workOrder', 'auditor'])->latest()->paginate(15);

        return view('audits.index', compact('audits'));
    }

    public function create(): View
    {
        return view('audits.create');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:internal,financial,project'],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'title' => ['required', 'string', 'max:255'],
            'audit_date' => ['required', 'date'],
            'findings' => ['nullable', 'string'],
        ]);

        Audit::create($data + ['auditor_id' => $request->user()->id, 'status' => 'completed']);

        return redirect()->route('audits.index')->with('success', 'Audit recorded.');
    }

    public function show(Audit $audit): View
    {
        $audit->load(['workOrder', 'auditor']);

        return view('audits.show', compact('audit'));
    }
}
