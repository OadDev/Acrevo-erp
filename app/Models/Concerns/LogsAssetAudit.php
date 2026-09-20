<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Auth;
use Spatie\Activitylog\Models\Activity;

/**
 * Enriches every Activity Log entry created for an Equipment & Asset
 * Management model with the causer's role and the work order/site the
 * record belongs to - on top of Spatie's own User/Action/Subject/
 * Previous-New-Value/Date-Time columns, this is what the module's Audit
 * Log spec calls "Role" and "WO-Site".
 */
trait LogsAssetAudit
{
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->merge([
            'role' => Auth::user()?->roles->pluck('name')->join(', '),
            'work_order_id' => $this->work_order_id ?? $this->current_work_order_id ?? $this->to_work_order_id ?? null,
        ])->filter(fn ($value) => filled($value));
    }
}
