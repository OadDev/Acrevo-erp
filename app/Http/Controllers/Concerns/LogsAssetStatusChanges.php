<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Asset;
use App\Models\AssetStatusLog;
use App\Models\User;

trait LogsAssetStatusChanges
{
    /**
     * Writes an entry into the same undeletable AssetStatusLog table used
     * for manual status updates, then applies the new status - shared by
     * every automatic status transition (repair reported/completed/
     * cancelled, verification finding an asset missing or damaged) so the
     * asset's Status History always tells one unified story regardless of
     * what triggered the change.
     */
    protected function transitionAssetStatus(Asset $asset, string $newStatus, User $user, string $reason): void
    {
        if ($asset->status === $newStatus) {
            return;
        }

        AssetStatusLog::create([
            'asset_id' => $asset->id,
            'previous_status' => $asset->status,
            'new_status' => $newStatus,
            'updated_by' => $user->id,
            'role' => $user->roles->pluck('name')->join(', ') ?: null,
            'work_order_id' => $asset->current_work_order_id,
            'reason' => $reason,
        ]);

        $asset->update(['status' => $newStatus]);
    }
}
