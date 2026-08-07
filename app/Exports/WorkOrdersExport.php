<?php

namespace App\Exports;

use App\Models\WorkOrder;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WorkOrdersExport implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        return WorkOrder::with('client')->latest()->get()->map(fn (WorkOrder $workOrder) => [
            $workOrder->work_order_no,
            $workOrder->title,
            $workOrder->client->name,
            $workOrder->priority,
            $workOrder->status,
            optional($workOrder->start_date)->format('Y-m-d'),
            optional($workOrder->deadline)->format('Y-m-d'),
            $workOrder->budget_amount,
        ]);
    }

    public function headings(): array
    {
        return ['Work Order No', 'Title', 'Client', 'Priority', 'Status', 'Start Date', 'Deadline', 'Budget'];
    }
}
