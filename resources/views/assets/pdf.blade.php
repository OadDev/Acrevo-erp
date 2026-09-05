<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 12px; color: #1f2937; }
        h1 { font-size: 20px; margin-bottom: 0; color: #4338ca; }
        h2 { font-size: 13px; margin: 18px 0 6px; color: #374151; }
        .muted { color: #6b7280; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border-bottom: 1px solid #e5e7eb; padding: 6px 8px; text-align: left; }
        th { background: #f9fafb; text-transform: uppercase; font-size: 10px; color: #6b7280; }
        .text-right { text-align: right; }
        .badge { display: inline-block; padding: 2px 10px; border-radius: 999px; font-size: 11px; font-weight: bold; background: #f3f4f6; color: #4b5563; }
    </style>
</head>
<body>
    <table style="border: none; margin-bottom: 8px;">
        <tr style="border: none;">
            <td style="border: none; width: 60%;">
                <h1>{{ config('app.name') }}</h1>
                <p class="muted">Asset Details Report</p>
            </td>
            <td style="border: none; width: 40%;" class="text-right">
                <span class="badge">{{ $asset->asset_code }}</span>
            </td>
        </tr>
    </table>

    <h2>Asset Details</h2>
    <table>
        <tr><td>Asset ID</td><td>{{ $asset->asset_code }}</td></tr>
        <tr><td>Equipment Name</td><td>{{ $asset->name }}</td></tr>
        <tr><td>Category</td><td>{{ $asset->category ?: '—' }}</td></tr>
        <tr><td>Brand</td><td>{{ $asset->brand ?: '—' }}</td></tr>
        <tr><td>Model</td><td>{{ $asset->model ?: '—' }}</td></tr>
        <tr><td>Serial Number</td><td>{{ $asset->serial_number ?: '—' }}</td></tr>
        <tr><td>Current Status</td><td>{{ ucwords(str_replace('_', ' ', $asset->status)) }}</td></tr>
        <tr><td>Current Location</td><td>{{ $asset->current_location === 'work_order' && $asset->currentWorkOrder ? $asset->currentWorkOrder->work_order_no : ucwords(str_replace('_', ' ', $asset->current_location)) }}</td></tr>
        <tr><td>Current Work Order</td><td>{{ $asset->currentWorkOrder->work_order_no ?? '—' }}</td></tr>
    </table>

    <h2>Purchase Details</h2>
    <table>
        <tr><td>Purchase Date</td><td>{{ optional($asset->purchase_date)->format('d M Y') ?? '—' }}</td></tr>
        <tr><td>Purchase Cost</td><td>{{ $asset->purchase_cost !== null ? 'Rs. '.number_format($asset->purchase_cost, 2) : '—' }}</td></tr>
        <tr><td>Supplier</td><td>{{ $asset->supplier ?: '—' }}</td></tr>
        <tr><td>Invoice Number</td><td>{{ $asset->invoice_number ?: '—' }}</td></tr>
    </table>

    <h2>Warranty Details</h2>
    <table>
        <tr><td>Warranty Start</td><td>{{ optional($asset->warranty_start)->format('d M Y') ?? '—' }}</td></tr>
        <tr><td>Warranty End</td><td>{{ optional($asset->warranty_end)->format('d M Y') ?? '—' }}</td></tr>
        <tr><td>Warranty Status</td><td>{{ $asset->warrantyStatus() ? ucwords(str_replace('_', ' ', $asset->warrantyStatus())) : '—' }}</td></tr>
        <tr><td>Warranty Provider</td><td>{{ $asset->warranty_provider ?: '—' }}</td></tr>
        <tr><td>Warranty Card Details</td><td>{{ $asset->warranty_card_details ?: '—' }}</td></tr>
    </table>

    <h2>Current Assignment</h2>
    <table>
        <tr><td>Site</td><td>{{ $asset->currentWorkOrder?->site?->site_no ?? '—' }}</td></tr>
        <tr><td>Work Order</td><td>{{ $asset->currentWorkOrder?->work_order_no ?? '—' }}</td></tr>
    </table>

    <h2>Condition</h2>
    <table>
        <tr><td>Current Condition</td><td>{{ $asset->condition ?: '—' }}</td></tr>
        <tr><td>Remarks</td><td>{{ $asset->remarks ?: '—' }}</td></tr>
    </table>

    <h2>Movement History</h2>
    @if ($asset->movements->isEmpty())
        <p class="muted">No movements recorded yet.</p>
    @else
        <table>
            <thead>
                <tr><th>Date</th><th>From</th><th>To</th><th>Type</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach ($asset->movements as $movement)
                    <tr>
                        <td>{{ $movement->moved_at->format('d M Y') }}</td>
                        <td>{{ $movement->locationLabel($movement->from_location, $movement->fromWorkOrder) }}</td>
                        <td>{{ $movement->locationLabel($movement->to_location, $movement->toWorkOrder) }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $movement->type)) }}</td>
                        <td>{{ ucwords($movement->status) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Repair History</h2>
    @if ($asset->repairs->isEmpty())
        <p class="muted">No repairs recorded yet.</p>
    @else
        <table>
            <thead>
                <tr><th>Reported</th><th>Completed</th><th>Type</th><th>Issue</th><th>Technician / Vendor</th><th>Warranty</th><th class="text-right">Cost</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach ($asset->repairs as $repair)
                    <tr>
                        <td>{{ $repair->reported_date->format('d M Y') }}</td>
                        <td>{{ optional($repair->completed_date)->format('d M Y') ?? '—' }}</td>
                        <td>{{ ucwords($repair->repair_type) }}</td>
                        <td>{{ $repair->issue_description }}</td>
                        <td>{{ $repair->technician_vendor ?: '—' }}</td>
                        <td>{{ $repair->is_warranty_repair ? 'Warranty' : 'Paid' }}</td>
                        <td class="text-right">{{ $repair->cost !== null ? 'Rs. '.number_format($repair->cost, 2) : '—' }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $repair->status)) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <h2>Status History</h2>
    @if ($asset->statusLogs->isEmpty())
        <p class="muted">No status changes recorded yet.</p>
    @else
        <table>
            <thead>
                <tr><th>Date</th><th>From</th><th>To</th><th>Updated By</th><th>Site / WO</th><th>Reason</th></tr>
            </thead>
            <tbody>
                @foreach ($asset->statusLogs->sortBy('created_at') as $log)
                    <tr>
                        <td>{{ $log->created_at->format('d M Y') }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $log->previous_status)) }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $log->new_status)) }}</td>
                        <td>{{ $log->updatedBy?->name }}</td>
                        <td>{{ $log->workOrder?->work_order_no ?? '—' }}</td>
                        <td>{{ $log->reason ?: '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</body>
</html>
