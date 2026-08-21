<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    protected $guard_name = 'web';

    protected $fillable = [
        'employee_code',
        'name',
        'email',
        'phone',
        'avatar_path',
        'department_id',
        'designation',
        'is_active',
        'password',
        'created_by',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'is_active', 'department_id', 'designation'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employee(): HasOne
    {
        return $this->hasOne(Employee::class);
    }

    public function assignedClients(): HasMany
    {
        return $this->hasMany(Client::class, 'assigned_sales_user_id');
    }

    public function clientLogin(): HasOne
    {
        return $this->hasOne(ClientLogin::class);
    }

    public function client(): ?Client
    {
        return $this->clientLogin?->client;
    }

    public function subcontractorProfile(): HasOne
    {
        return $this->hasOne(SubcontractorProfile::class);
    }

    public function conversationParticipations(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function conversations()
    {
        return Conversation::whereIn('id', $this->conversationParticipations()->pluck('conversation_id'));
    }

    public function unreadConversationCount(): int
    {
        return $this->conversationParticipations()
            ->get()
            ->filter(function (ConversationParticipant $p) {
                return Message::where('conversation_id', $p->conversation_id)
                    ->where('user_id', '!=', $this->id)
                    ->when($p->last_read_at, fn ($q) => $q->where('created_at', '>', $p->last_read_at))
                    ->exists();
            })
            ->count();
    }
}
