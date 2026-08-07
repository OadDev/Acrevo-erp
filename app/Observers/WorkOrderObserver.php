<?php

namespace App\Observers;

use App\Models\WorkOrder;
use Illuminate\Support\Facades\Auth;

class WorkOrderObserver
{
    public function created(WorkOrder $workOrder): void
    {
        $workOrder->statusLogs()->create([
            'from_status' => null,
            'to_status' => $workOrder->status,
            'changed_by' => Auth::id(),
            'remarks' => 'Work order created.',
            'changed_at' => now(),
        ]);
    }
}
