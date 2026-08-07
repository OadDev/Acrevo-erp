<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExecutiveTeamMember extends Model
{
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
        return $this->belongsTo(Employee::class);
    }
}
