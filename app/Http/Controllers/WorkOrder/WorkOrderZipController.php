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

        return response()->download($zipPath, "{$workOrder->work_order_no}.zip")->deleteFileAfterSend();
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

        return response()->download($zipPath, "{$workOrder->work_order_no}-{$label}.zip")->deleteFileAfterSend();
    }
}
