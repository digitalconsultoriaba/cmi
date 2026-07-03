<?php

namespace App\Policies;

use App\Domain\Events\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    /** Dono = participante (conta ou e-mail) ou comprador do pedido. */
    public function view(User $user, Ticket $ticket): bool
    {
        return $ticket->participant_user_id === $user->id
            || ($ticket->participant_email !== null
                && mb_strtolower($ticket->participant_email) === $user->email)
            || $ticket->order?->buyer_user_id === $user->id;
    }

    public function receipt(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }
}
