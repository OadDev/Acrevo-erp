<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ExecutiveTeam extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = ['team_number', 'name', 'team_leader_id', 'is_active', 'created_by'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function teamLeader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'team_leader_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ExecutiveTeamMember::class);
    }

    public function workOrderAssignments(): HasMany
    {
        return $this->hasMany(WorkOrderExecutiveTeam::class);
    }
}
