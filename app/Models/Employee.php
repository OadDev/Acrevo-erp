<?php

namespace App\Models;

use App\Models\Concerns\HasSequenceNumber;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

class Employee extends Model implements HasMedia
{
    use HasFactory, HasSequenceNumber, InteractsWithMedia, SoftDeletes;

    protected $sequencePrefix = 'EMP';

    protected $sequenceColumn = 'employee_code';

    /**
     * Roles whose attendance/payroll is tracked as "Employee Payroll" (HR >
     * Attendance) rather than "WO Workers Payroll" (a work order's M.Book).
     */
    public const STAFF_ROLES = ['Sales', 'HR', 'Finance', 'Executive Team Leader', 'QC Officer'];

    protected $fillable = [
        'employee_code', 'user_id', 'name', 'phone', 'date_of_birth', 'email', 'designation',
        'qualification', 'experience_summary', 'department_id', 'skill_set', 'employment_type', 'joining_date',
        'relieving_date', 'status', 'salary_type', 'salary_amount', 'address',
        'emergency_contact_name', 'emergency_contact_phone', 'exit_notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'skill_set' => 'array',
            'date_of_birth' => 'date',
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

    /**
     * Employees whose attendance belongs on the WO Workers Payroll: an
     * unregistered worker (no login) or one linked to a User with the
     * Worker role. Mirrors the Executive Team member eligibility rule.
     */
    public function scopeWorkOrderWorkers(Builder $query): Builder
    {
        return $query->where(fn ($q) => $q->whereNull('user_id')->orWhereHas('user', fn ($q2) => $q2->role('Worker')));
    }

    /**
     * Employees whose attendance belongs on the Employee Payroll: linked to
     * a User with one of the STAFF_ROLES (Sales, HR, Finance, Executive
     * Team Leader, QC Officer).
     */
    public function scopeStaff(Builder $query): Builder
    {
        return $query->whereHas('user', fn ($q) => $q->role(self::STAFF_ROLES));
    }
}
