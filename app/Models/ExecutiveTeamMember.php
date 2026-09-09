<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExecutiveTeamMember extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = ['executive_team_id', 'employee_id', 'role_in_team', 'joined_at', 'left_at'];

    protected function casts(): array
    {
        return ['joined_at' => 'date', 'left_at' => 'date'];
    }

    public function executiveTeam(): BelongsTo
    {
        return $this->belongsTo(ExecutiveTeam::class);
    }

    public function employee(): BelongsTo
    {
        // withTrashed() so a permanently removed worker's historical
        // entries still show their name instead of crashing on null.
        return $this->belongsTo(Employee::class)->withTrashed();
    }
}
