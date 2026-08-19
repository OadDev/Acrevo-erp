<?php

namespace App\Http\Controllers\WorkOrder;

use App\Http\Controllers\Concerns\LoadsWorkOrderPdfRelations;
use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Support\WorkOrderPdfSections;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class WorkOrderPdfController extends Controller
{
    use LoadsWorkOrderPdfRelations;

    public function section(Request $request, WorkOrder $workOrder, string $section)
    {
        $this->authorize('view', $workOrder);

        $available = WorkOrderPdfSections::forUser($request->user());
        abort_unless(array_key_exists($section, $available), 404);

        $this->loadWorkOrderPdfRelations($workOrder);

        $pdf = Pdf::loadView('work-orders.pdf.section', compact('workOrder', 'section'));

        return $pdf->download("{$workOrder->work_order_no}-{$section}.pdf");
    }

    public function full(Request $request, WorkOrder $workOrder)
    {
        $this->authorize('view', $workOrder);

        $sections = array_keys(WorkOrderPdfSections::forUser($request->user()));

        $this->loadWorkOrderPdfRelations($workOrder);

        $pdf = Pdf::loadView('work-orders.pdf.full', compact('workOrder', 'sections'));

        return $pdf->download("{$workOrder->work_order_no}.pdf");
    }
}
