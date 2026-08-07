<?php

namespace App\Exports;

use App\Models\Ticket;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class TicketsExport implements FromCollection, WithHeadings
{
    public function collection(): Collection
    {
        return Ticket::with('workOrder')->latest()->get()->map(fn (Ticket $ticket) => [
            $ticket->ticket_no,
            $ticket->workOrder->work_order_no,
            $ticket->type,
            $ticket->priority,
            $ticket->status,
            $ticket->created_at->format('Y-m-d'),
        ]);
    }

    public function headings(): array
    {
        return ['Ticket No', 'Work Order', 'Type', 'Priority', 'Status', 'Raised On'];
    }
}
