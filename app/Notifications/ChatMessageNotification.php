<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Notifications\Notification;

class ChatMessageNotification extends Notification
{
    public function __construct(
        public Message $message,
        public Conversation $conversation,
        public User $sender,
        public bool $mentioned = false,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $preview = str($this->message->body ?: 'sent an attachment')->limit(80);

        return [
            'message' => $this->mentioned
                ? "{$this->sender->name} mentioned you: {$preview}"
                : "{$this->sender->name}: {$preview}",
            'url' => route('chat.index', ['conversation' => $this->conversation->id]),
        ];
    }
}
