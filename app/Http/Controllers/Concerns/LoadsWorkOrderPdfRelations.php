<?php

namespace App\Http\Controllers\Concerns;

use App\Models\WorkOrder;

trait LoadsWorkOrderPdfRelations
{
    /**
     * The full relation set a work order needs loaded to render either its
     * show page or any of its PDF exports - kept in one place so the two
     * never drift out of sync.
     */
    protected function loadWorkOrderPdfRelations(WorkOrder $workOrder): WorkOrder
    {
        return $workOrder->load([
            'client', 'quotation', 'site.media', 'statusLogs.changedBy', 'executiveTeams.executiveTeam.teamLeader',
            'tickets', 'qcInspections.inspectedBy',
            'dailyChecklists' => fn ($q) => $q->latest(),
            'dailyChecklists.checklistItems.doneBy', 'dailyChecklists.checklistItems.media',
            'dailyChecklists.executiveTeam',
            'dailyProgressReports' => fn ($q) => $q->latest(),
            'materialEntries.addedBy', 'materialUsageEntries.addedBy', 'labourEntries.employee', 'timeSchedules', 'measurementBooks.items', 'ledgers.media', 'ledgers.createdBy',
            'companyLedgers.media', 'companyLedgers.createdBy',
            'summaries' => fn ($q) => $q->orderBy('entry_date'),
            'attendances.employee', 'attendances.markedBy',
            'children', 'parent', 'clientReviews',
            'media',
            'approvalRequests.requestedBy', 'approvalRequests.requestedByClient', 'approvalRequests.respondedBy', 'approvalRequests.media', 'approvalRequests.workOrder.client',
        ]);
    }
}
