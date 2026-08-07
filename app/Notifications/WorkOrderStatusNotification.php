<?php

namespace App\Notifications;

use App\Models\WorkOrder;
use Illuminate\Notifications\Notification;

class WorkOrderStatusNotification extends Notification
{
    public function __construct(public WorkOrder $workOrder) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "Work Order {$this->workOrder->work_order_no} is now ".str($this->workOrder->status)->replace('_', ' ')->title(),
            'work_order_id' => $this->workOrder->id,
        ];
    }
}
