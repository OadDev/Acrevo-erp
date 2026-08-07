<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkOrderExecutiveTeam extends Model
{
    protected $table = 'work_order_executive_team';

    protected $fillable = ['work_order_id', 'executive_team_id', 'assigned_by', 'assigned_at', 'unassigned_at'];

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'unassigned_at' => 'datetime'];
    }

    public function workOrder(): BelongsTo
    {
        return $this->belongsTo(WorkOrder::class);
    }

    public function executiveTeam(): BelongsTo
    {
        return $this->belongsTo(ExecutiveTeam::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
