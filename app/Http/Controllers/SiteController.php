<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LoadsWorkOrderPdfRelations;
use App\Models\Client;
use App\Models\Site;
use App\Services\WorkOrderZipExporter;
use App\Support\WorkOrderPdfSections;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class SiteController extends Controller
{
    use LoadsWorkOrderPdfRelations;

    public function index(Request $request): View
    {
        $sites = Site::query()
            ->with('client')
            ->withCount('workOrders')
            ->when($request->get('q'), fn ($q, $search) => $q->where(fn ($q2) => $q2
                ->where('site_no', 'like', "%{$search}%")
                ->orWhere('address', 'like', "%{$search}%")
                ->orWhere('city', 'like', "%{$search}%")
                ->orWhereHas('client', fn ($q3) => $q3->where('name', 'like', "%{$search}%"))))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('sites.index', compact('sites'));
    }

    public function show(Site $site): View
    {
        $site->load(['client', 'quotation', 'media']);
        $workOrders = $site->workOrders()->latest()->paginate(15);

        return view('sites.show', compact('site', 'workOrders'));
    }

    public function create(Request $request): View
    {
        $client = $request->get('client_id') ? Client::findOrFail($request->get('client_id')) : null;
        $clients = Client::orderBy('name')->get();

        return view('sites.create', compact('client', 'clients'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'site_contact_name' => ['nullable', 'string', 'max:150'],
            'site_contact_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $site = Site::create($data + ['created_by' => $request->user()->id]);

        return redirect()->route('sites.show', $site)->with('success', 'Site added to client.');
    }

    public function destroy(Site $site): RedirectResponse
    {
        $this->authorizeAdminOnly();

        $remainingWorkOrders = $site->workOrders()->count();

        abort_if($remainingWorkOrders > 0, 422, "This site still has {$remainingWorkOrders} work order(s). Remove all work orders under this site before removing the site.");

        $site->delete();

        return redirect()->route('sites.index')->with('success', 'Site removed.');
    }

    public function update(Request $request, Site $site): RedirectResponse
    {
        $data = $request->validate([
            'address' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'site_contact_name' => ['nullable', 'string', 'max:150'],
            'site_contact_phone' => ['nullable', 'string', 'max:20'],
        ]);

        $site->update($data);

        return back()->with('success', 'Site details updated.');
    }

    public function pdf(Request $request, Site $site)
    {
        $site->load('client');

        $workOrders = $site->workOrders()->orderBy('created_at')->get();
        $workOrders->each(fn ($workOrder) => $this->loadWorkOrderPdfRelations($workOrder));

        $sections = array_keys(WorkOrderPdfSections::forUser($request->user()));

        $pdf = Pdf::loadView('sites.pdf', compact('site', 'workOrders', 'sections'));

        return $pdf->download("{$site->site_no}-work-orders.pdf");
    }

    public function zip(Request $request, Site $site, WorkOrderZipExporter $exporter): BinaryFileResponse
    {
        $site->load('client');

        $workOrders = $site->workOrders()->orderBy('created_at')->get();
        $workOrders->each(fn ($workOrder) => $this->loadWorkOrderPdfRelations($workOrder));

        $sections = array_keys(WorkOrderPdfSections::forUser($request->user()));

        $zipPath = sys_get_temp_dir().'/site-zip-'.Str::random(20).'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $usedFolders = [];
        foreach ($workOrders as $workOrder) {
            $folder = $exporter->safeName($workOrder->work_order_no);
            $suffix = 1;
            while (in_array($folder, $usedFolders, true)) {
                $folder = $exporter->safeName($workOrder->work_order_no).' ('.(++$suffix).')';
            }
            $usedFolders[] = $folder;

            $exporter->addWorkOrder($zip, $workOrder, $sections, $folder);
        }

        $zip->close();

        return response()->download($zipPath, "{$site->site_no}-work-orders.zip")->deleteFileAfterSend();
    }

    public function complete(Site $site): RedirectResponse
    {
        abort_if($site->status === 'completed', 422, 'This site is already marked completed.');

        $openWorkOrders = $site->workOrders()->whereNotIn('status', ['completed', 'cancelled'])->count();

        if ($openWorkOrders > 0) {
            return back()->withErrors(['site' => "This site still has {$openWorkOrders} work order(s) that aren't completed or cancelled yet. Finish or cancel them before marking the site completed."]);
        }

        $site->update(['status' => 'completed', 'completed_at' => now()]);

        return back()->with('success', 'Site marked as completed and handed over to the client.');
    }
}
