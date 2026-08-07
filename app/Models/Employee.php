<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Employee extends Model implements HasMedia
{
    use HasFactory, InteractsWithMedia, SoftDeletes;

    protected $fillable = [
        'employee_code', 'user_id', 'name', 'phone', 'email', 'designation',
        'department_id', 'skill_set', 'employment_type', 'joining_date',
        'relieving_date', 'status', 'salary_type', 'salary_amount', 'address',
        'emergency_contact_name', 'emergency_contact_phone', 'exit_notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'skill_set' => 'array',
            'joining_date' => 'date',
            'relieving_date' => 'date',
            'salary_amount' => 'decimal:2',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents');
        $this->addMediaCollection('id_card');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function payrolls(): HasMany
    {
        return $this->hasMany(Payroll::class);
    }

    public function salaryAdvances(): HasMany
    {
        return $this->hasMany(SalaryAdvance::class);
    }

    public function benefits(): HasMany
    {
        return $this->hasMany(EmployeeBenefit::class);
    }

    public function executiveTeamMemberships(): HasMany
    {
        return $this->hasMany(ExecutiveTeamMember::class);
    }
}
