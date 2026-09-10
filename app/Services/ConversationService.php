<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\ChatMessageNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ConversationService
{
    /**
     * One discussion thread per record - found by its polymorphic subject,
     * created on first use so untouched records don't clutter the table.
     * Anyone who can view the record (already checked by the calling
     * controller's own permission gate before this is called) is silently
     * added as a participant the moment they open its Discussion section -
     * there's no separate invite step, since access to the discussion
     * should simply mirror access to the record itself.
     */
    public function discussionFor(Model $subject, ?User $viewer = null): Conversation
    {
        $conversation = Conversation::firstOrCreate([
            'type' => 'discussion',
            'subject_type' => $subject::class,
            'subject_id' => (string) $subject->getKey(),
        ]);

        if ($viewer) {
            $this->addParticipant($conversation, $viewer);
        }

        return $conversation->load(['participants.user', 'messages.user', 'messages.media']);
    }

    public function directBetween(User $a, User $b): Conversation
    {
        $existing = Conversation::where('type', 'direct')
            ->whereHas('participants', fn ($q) => $q->where('user_id', $a->id))
            ->whereHas('participants', fn ($q) => $q->where('user_id', $b->id))
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($a, $b) {
            $conversation = Conversation::create(['type' => 'direct', 'created_by' => $a->id]);
            $this->addParticipant($conversation, $a);
            $this->addParticipant($conversation, $b);

            return $conversation;
        });
    }

    public function addParticipant(Conversation $conversation, User $user): void
    {
        // last_read_at stays null until the participant actually opens the
        // conversation - anything sent before or right after they join
        // still counts as unread for them.
        $conversation->participants()->firstOrCreate(
            ['user_id' => $user->id],
            ['joined_at' => now()]
        );
    }

    /**
     * Shared by the internal chat/discussion module and the client portal's
     * Work Order Discussion - same message, attachments, unread tracking,
     * and mention notifications either way.
     *
     * @param  array<int, \Illuminate\Http\UploadedFile>  $files
     */
    public function postMessage(Conversation $conversation, User $user, ?string $body, array $files = []): Message
    {
        $message = $conversation->messages()->create([
            'user_id' => $user->id,
            'body' => $body,
        ]);

        foreach ($files as $file) {
            $message->addMedia($file)->toMediaCollection('attachments');
        }

        $conversation->markReadFor($user);
        $this->recordMentionsAndNotify($message, $conversation, $user);
        $conversation->touch();

        return $message;
    }

    /**
     * Extracts @Full Name mentions from a message body against the
     * conversation's own participant list (kept small, no site-wide user
     * enumeration), tags the mentioned users on the message, and notifies
     * them - in-app only, via the existing database notification channel.
     */
    public function recordMentionsAndNotify(Message $message, Conversation $conversation, User $sender): void
    {
        $participants = $conversation->participants()->with('user')->get()
            ->pluck('user')->filter()->reject(fn (User $u) => $u->id === $sender->id);

        $body = (string) $message->body;
        $mentioned = $participants->filter(fn (User $u) => str_contains($body, '@'.$u->name));

        foreach ($mentioned as $user) {
            $message->mentions()->firstOrCreate(['user_id' => $user->id]);
        }

        foreach ($participants as $user) {
            $user->notify(new ChatMessageNotification($message, $conversation, $sender, $mentioned->contains('id', $user->id)));
        }
    }
}
