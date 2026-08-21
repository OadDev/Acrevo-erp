<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Conversation extends Model
{
    public const TYPES = ['direct', 'group', 'discussion'];

    protected $fillable = ['type', 'name', 'subject_type', 'subject_id', 'created_by'];

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function participants(): HasMany
    {
        return $this->hasMany(ConversationParticipant::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function latestMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function hasParticipant(User $user): bool
    {
        return $this->participants()->where('user_id', $user->id)->exists();
    }

    public function unreadCountFor(User $user): int
    {
        $lastReadAt = $this->participants()->where('user_id', $user->id)->value('last_read_at');

        return $this->messages()
            ->where('user_id', '!=', $user->id)
            ->when($lastReadAt, fn ($q) => $q->where('created_at', '>', $lastReadAt))
            ->count();
    }

    public function markReadFor(User $user): void
    {
        $this->participants()->where('user_id', $user->id)->update(['last_read_at' => now()]);
    }

    public function displayNameFor(User $user): string
    {
        if ($this->type === 'group') {
            return $this->name ?: 'Group Chat';
        }

        if ($this->type === 'discussion') {
            return $this->name ?: ($this->subject ? class_basename($this->subject_type).' Discussion' : 'Discussion');
        }

        $other = $this->participants()->where('user_id', '!=', $user->id)->with('user')->first();

        return $other?->user?->name ?? 'Direct Message';
    }
}
