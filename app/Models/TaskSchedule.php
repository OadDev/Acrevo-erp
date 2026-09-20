<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class TaskSchedule extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    protected $fillable = [
        'title', 'description', 'assigned_to_user_id', 'assignee_role',
        'frequency', 'day_of_week', 'day_of_month', 'month_of_year',
        'verifier_user_id', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function assignedToUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_user_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function targetsUser(User $user): bool
    {
        if ($this->assigned_to_user_id) {
            return $this->assigned_to_user_id === $user->id;
        }

        return $this->assignee_role && $user->hasRole($this->assignee_role);
    }

    /**
     * A stable key identifying the current period (day/week/month/year)
     * a reference date falls in, used to avoid generating the same
     * recurring task instance twice for that period.
     */
    public function periodKeyFor(Carbon $reference): string
    {
        return match ($this->frequency) {
            'daily' => $reference->format('Y-m-d'),
            'weekly' => $reference->format('o-\WW'),
            'monthly' => $reference->format('Y-m'),
            'yearly' => $reference->format('Y'),
            default => $reference->format('Y-m-d'),
        };
    }

    /**
     * The due date within the period containing $reference, or null if
     * that due day hasn't arrived yet this period (so nothing should be
     * generated for it yet).
     */
    public function dueDateFor(Carbon $reference): ?Carbon
    {
        return match ($this->frequency) {
            'daily' => $reference->copy(),
            'weekly' => $this->weeklyDueDate($reference),
            'monthly' => $this->monthlyDueDate($reference),
            'yearly' => $this->yearlyDueDate($reference),
            default => null,
        };
    }

    private function weeklyDueDate(Carbon $reference): ?Carbon
    {
        $due = $reference->copy()->startOfWeek(Carbon::SUNDAY)->addDays($this->day_of_week ?? 0);

        return $reference->gte($due) ? $due : null;
    }

    private function monthlyDueDate(Carbon $reference): ?Carbon
    {
        $day = min($this->day_of_month ?? 1, $reference->copy()->endOfMonth()->day);
        $due = $reference->copy()->startOfMonth()->addDays($day - 1);

        return $reference->gte($due) ? $due : null;
    }

    private function yearlyDueDate(Carbon $reference): ?Carbon
    {
        $due = $reference->copy()->month($this->month_of_year ?? 1)->startOfMonth();
        $day = min($this->day_of_month ?? 1, $due->copy()->endOfMonth()->day);
        $due->addDays($day - 1);

        return $reference->gte($due) ? $due : null;
    }
}
