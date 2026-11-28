<?php

namespace App\Policies;

use App\Models\TicketComment;
use App\Models\User;

/**
 * Correcting or removing a comment (or its attachments) someone already
 * posted - always scoped to the commenter's own comment. Admin bypasses
 * that scoping, same as every other "edit your own" ability in the app.
 * A client commenting through the portal needs no dedicated permission -
 * client_portal.access already covers managing their own comment, the
 * same way it already blends into Ticket view/comment access elsewhere;
 * staff need the matching tickets.comments.* / tickets.comment_attachments.*
 * permission on top of owning the comment.
 */
class TicketCommentPolicy
{
    public function update(User $user, TicketComment $comment): bool
    {
        return $this->ownsAnd($user, $comment, 'tickets.comments.edit');
    }

    public function delete(User $user, TicketComment $comment): bool
    {
        return $this->ownsAnd($user, $comment, 'tickets.comments.delete');
    }

    public function manageAttachments(User $user, TicketComment $comment): bool
    {
        return $this->ownsAnd($user, $comment, 'tickets.comment_attachments.edit');
    }

    public function deleteAttachment(User $user, TicketComment $comment): bool
    {
        return $this->ownsAnd($user, $comment, 'tickets.comment_attachments.delete');
    }

    private function ownsAnd(User $user, TicketComment $comment, string $permission): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if ($comment->user_id !== $user->id) {
            return false;
        }

        return $user->can($permission) || $user->can('client_portal.access');
    }
}
