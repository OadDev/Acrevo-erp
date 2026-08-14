<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tickets.view') || $user->can('client_portal.access');
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->can('tickets.view')) {
            return true;
        }

        if ($user->can('client_portal.access')) {
            return $ticket->workOrder->client_id === $user->client()?->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->can('tickets.create') || $user->can('client_portal.access');
    }

    public function update(User $user, Ticket $ticket): bool
    {
        if ($ticket->locked_at) {
            return false;
        }

        if ($ticket->raised_by_type === 'client') {
            return $user->hasRole(['Sales', 'Executive Team Leader']);
        }

        // Raised internally (Sales, Executive Team Leader, or whoever else has
        // tickets.create) - only the person who raised it may edit it.
        return $ticket->raised_by === $user->id;
    }

    public function lock(User $user, Ticket $ticket): bool
    {
        return $this->update($user, $ticket);
    }
}
