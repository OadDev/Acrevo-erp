<?php

namespace App\Http\Controllers;

use App\Exports\PayrollExport;
use App\Exports\TicketsExport;
use App\Exports\WorkOrdersExport;
use App\Models\Employee;
use App\Models\Ticket;
use App\Models\WorkOrder;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReportController extends Controller
{
    public function index(): View
    {
        $summary = [
            'work_orders_by_status' => WorkOrder::selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'tickets_by_type' => Ticket::selectRaw('type, count(*) as total')->groupBy('type')->pluck('total', 'type'),
            'active_employees' => Employee::where('status', 'active')->count(),
        ];

        return view('reports.index', compact('summary'));
    }

    public function exportWorkOrders(): BinaryFileResponse
    {
        return Excel::download(new WorkOrdersExport, 'work-orders.xlsx');
    }

    public function exportPayroll(Request $request): BinaryFileResponse
    {
        $month = (int) $request->get('month', now()->month);
        $year = (int) $request->get('year', now()->year);

        return Excel::download(new PayrollExport($month, $year), "payroll-{$year}-{$month}.xlsx");
    }

    public function exportTickets(): BinaryFileResponse
    {
        return Excel::download(new TicketsExport, 'tickets.xlsx');
    }
}
