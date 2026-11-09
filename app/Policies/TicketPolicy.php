<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.all_view') || $user->can('tickets.assigned_view') || $user->can('client_portal.access');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->can('tickets.all_view')) {
            return true;
        }

        if ($user->can('tickets.assigned_view')) {
            return $ticket->hasAssignedWorkOrderFor($user);
        }

        if ($user->can('client_portal.access')) {
            return $ticket->workOrder->client_id === $user->client()?->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.assigned_create') || $user->can('client_portal.access');
    }

    // Comment/reply - discussion only, no edit/delete rights.
    // tickets.all_view is global visibility *and* the ability to comment
    // on anything it can see, unscoped - it's meant to be the single
    // "sees and can direct every ticket company-wide" permission, not
    // read-only. tickets.assigned_manage alone stays scoped to this
    // ticket's work order, same as before.
    public function comment(User $user, Ticket $ticket): bool
    {
        if ($user->can('tickets.all_view')) {
            return true;
        }

        return $user->can('tickets.assigned_manage') && $ticket->hasAssignedWorkOrderFor($user);
    }

    // Editing a ticket's own fields (title/type/priority/etc.) and its
    // attachments. Admin always bypasses, including a locked ticket, so
    // incorrect/incomplete information doesn't stay wrong in front of the
    // client. Everyone else needs tickets.assigned_edit on this ticket's
    // work order, and the ticket must not be locked.
    public function update(User $user, Ticket $ticket): bool
    {
        if ($user->hasRole('Admin')) {
            return true;
        }

        if (! $user->can('tickets.assigned_edit')) {
            return false;
        }

        if ($ticket->locked_at) {
            return false;
        }

        return $ticket->hasAssignedWorkOrderFor($user);
    }

    // Changing a ticket's status (including resolving it) - same
    // permission as update(), but not blocked by a locked ticket, matching
    // the status action's previous independence from the lock state.
    public function manageStatus(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.assigned_edit') && $ticket->hasAssignedWorkOrderFor($user);
    }

    public function lock(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }

    public function delete(User $user, Ticket $ticket): bool
    {
        return $user->can('tickets.assigned_remove') && $ticket->hasAssignedWorkOrderFor($user);
    }
}
