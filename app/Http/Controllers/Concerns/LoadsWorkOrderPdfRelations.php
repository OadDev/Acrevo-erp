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
            'subContractors.user',
            'tickets.media', 'tickets.raisedBy', 'tickets.raisedByClient', 'tickets.department', 'tickets.assignedTo', 'qcInspections.inspectedBy',
            'dailyChecklists.checklistItems.doneBy', 'dailyChecklists.checklistItems.media',
            'dailyChecklists.executiveTeam',
            'dailyProgressReports.media',
            'materialEntries.addedBy', 'materialUsageEntries.addedBy', 'labourEntries.employee', 'timeSchedules', 'budgetItems', 'measurementBooks.items', 'ledgers.media', 'ledgers.createdBy',
            'companyLedgers.media', 'companyLedgers.createdBy',
            'summaries',
            'attendances.employee', 'attendances.markedBy',
            'children', 'parent', 'clientReviews',
            'media',
            'approvalRequests.requestedBy', 'approvalRequests.requestedByClient', 'approvalRequests.respondedBy', 'approvalRequests.media', 'approvalRequests.workOrder.client',
        ]);
    }
}
