<?php

namespace App\Http\Controllers;

use App\Models\Audit;
use App\Models\WorkOrder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Spatie\MediaLibrary\MediaCollections\Exceptions\FileIsTooBig;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class AuditController extends Controller
{
    private const FILE_RULES = ['file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,doc,docx'];

    public function index(Request $request): View
    {
        $audits = Audit::with(['workOrder', 'auditor', 'media'])
            ->when($request->get('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->get('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->get('work_order_id'), fn ($q, $id) => $q->where('work_order_id', $id))
            ->when($request->get('from'), fn ($q, $from) => $q->whereDate('audit_date', '>=', $from))
            ->when($request->get('to'), fn ($q, $to) => $q->whereDate('audit_date', '<=', $to))
            ->latest('audit_date')
            ->paginate(15)
            ->withQueryString();

        $workOrders = WorkOrder::orderByDesc('created_at')->limit(100)->get();

        return view('audits.index', compact('audits', 'workOrders'));
    }

    public function create(): View
    {
        $workOrders = WorkOrder::orderByDesc('created_at')->limit(100)->get();

        return view('audits.create', compact('workOrders'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:internal,financial,project'],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'title' => ['required', 'string', 'max:255'],
            'audit_date' => ['required', 'date'],
            'status' => ['nullable', 'in:scheduled,in_progress,completed'],
            'findings' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => self::FILE_RULES,
        ]);

        $audit = Audit::create(collect($data)->except('files')->all() + [
            'auditor_id' => $request->user()->id,
            'status' => $data['status'] ?? 'completed',
        ]);

        try {
            foreach ($request->file('files', []) as $file) {
                $audit->addMedia($file)->toMediaCollection('files');
            }
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
        }

        return redirect()->route('audits.index')->with('success', 'Audit recorded.');
    }

    public function show(Audit $audit): View
    {
        $audit->load(['workOrder', 'auditor', 'media']);

        return view('audits.show', compact('audit'));
    }

    public function edit(Audit $audit): View
    {
        $audit->load('media');
        $workOrders = WorkOrder::orderByDesc('created_at')->limit(100)->get();

        return view('audits.edit', compact('audit', 'workOrders'));
    }

    public function update(Request $request, Audit $audit): RedirectResponse
    {
        $data = $request->validate([
            'type' => ['required', 'in:internal,financial,project'],
            'work_order_id' => ['nullable', 'exists:work_orders,id'],
            'title' => ['required', 'string', 'max:255'],
            'audit_date' => ['required', 'date'],
            'status' => ['required', 'in:scheduled,in_progress,completed'],
            'findings' => ['nullable', 'string'],
            'files' => ['nullable', 'array'],
            'files.*' => self::FILE_RULES,
        ]);

        $audit->update(collect($data)->except('files')->all());

        try {
            foreach ($request->file('files', []) as $file) {
                $audit->addMedia($file)->toMediaCollection('files');
            }
        } catch (FileIsTooBig $e) {
            return back()->withErrors(['files' => 'One of those files is too large (max 20MB).']);
        }

        return redirect()->route('audits.index')->with('success', 'Audit updated.');
    }

    public function destroy(Audit $audit): RedirectResponse
    {
        $audit->delete();

        return redirect()->route('audits.index')->with('success', 'Audit removed.');
    }

    public function destroyMedia(Audit $audit, Media $media): RedirectResponse
    {
        abort_unless((string) $media->model_id === (string) $audit->id, 404);

        $media->delete();

        return back()->with('success', 'File removed.');
    }
}
