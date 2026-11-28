<?php

namespace App\Policies;

use App\Models\ApprovalRequestComment;
use App\Models\User;

/**
 * Same shape as TicketCommentPolicy - correcting or removing a comment
 * (or its attachments) is always scoped to the commenter's own comment,
 * Admin bypasses that scoping, and a client commenting through the
 * portal needs no dedicated permission (client_portal.access already
 * covers managing their own comment).
 */
class ApprovalRequestCommentPolicy
{
    public function update(User $user, ApprovalRequestComment $comment): bool
    {
        return $this->ownsAnd($user, $comment, 'approval_requests.comments.edit');
    }

    public function delete(User $user, ApprovalRequestComment $comment): bool
    {
        return $this->ownsAnd($user, $comment, 'approval_requests.comments.delete');
    }

    public function manageAttachments(User $user, ApprovalRequestComment $comment): bool
    {
        return $this->ownsAnd($user, $comment, 'approval_requests.comment_attachments.edit');
    }

    public function deleteAttachment(User $user, ApprovalRequestComment $comment): bool
    {
        return $this->ownsAnd($user, $comment, 'approval_requests.comment_attachments.delete');
    }

    private function ownsAnd(User $user, ApprovalRequestComment $comment, string $permission): bool
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
