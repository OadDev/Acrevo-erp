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
        return $user->can('tickets.manage');
    }
}
