<?php

namespace App\Listeners;

use App\Events\WorkOrderStatusChanged;
use App\Notifications\WorkOrderStatusNotification;

class NotifyWorkOrderStakeholders
{
    /**
     * Statuses that warrant nudging the people who care about this work order,
     * rather than firing a notification on every minor transition.
     */
    private const NOTIFIABLE_STATUSES = [
        'team_assigned', 'qc_failed', 'client_review', 'ticket_raised', 'completed',
    ];

    public function handle(WorkOrderStatusChanged $event): void
    {
        if (! in_array($event->workOrder->status, self::NOTIFIABLE_STATUSES, true)) {
            return;
        }

        $event->workOrder->creator?->notify(new WorkOrderStatusNotification($event->workOrder));
    }
}
