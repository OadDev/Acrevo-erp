<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Concerns\LoadsWorkOrderPdfRelations;
use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Services\WorkOrderZipExporter;
use App\Support\WorkOrderPdfSections;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class WorkOrderZipController extends Controller
{
    use LoadsWorkOrderPdfRelations;

    // Every ZIP is built fresh from the current DB state on each request -
    // never let a browser or intermediate proxy cache and replay a stale
    // copy (e.g. one that still contains a just-deleted attachment).
    private const NO_CACHE_HEADERS = ['Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0', 'Pragma' => 'no-cache'];

    public function __invoke(Request $request, WorkOrder $workOrder, WorkOrderZipExporter $exporter): BinaryFileResponse
    {
        $this->authorize('view', $workOrder);

        $this->loadWorkOrderPdfRelations($workOrder);

        $sections = array_keys(WorkOrderPdfSections::forUser($request->user()));

        $zipPath = sys_get_temp_dir().'/wo-zip-'.Str::random(20).'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $exporter->addWorkOrder($zip, $workOrder, $sections);
        $zip->close();

        return response()->download($zipPath, "{$workOrder->work_order_no}.zip", self::NO_CACHE_HEADERS)->deleteFileAfterSend();
    }

    public function section(Request $request, WorkOrder $workOrder, string $section, WorkOrderZipExporter $exporter): BinaryFileResponse
    {
        $this->authorize('view', $workOrder);

        $available = WorkOrderPdfSections::forUser($request->user());
        abort_unless(array_key_exists($section, $available), 404);

        $this->loadWorkOrderPdfRelations($workOrder);

        $zipPath = sys_get_temp_dir().'/wo-zip-'.Str::random(20).'.zip';

        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $exporter->addSection($zip, $workOrder, $section);
        $zip->close();

        $label = $exporter->safeName($available[$section]);

        return response()->download($zipPath, "{$workOrder->work_order_no}-{$label}.zip", self::NO_CACHE_HEADERS)->deleteFileAfterSend();
    }
}
