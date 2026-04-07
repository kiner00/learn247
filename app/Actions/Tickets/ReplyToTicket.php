<?php

namespace App\Actions\Tickets;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;

class ReplyToTicket
{
    public function execute(User $user, Ticket $ticket, array $data): TicketReply
    {
        return TicketReply::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $user->id,
            'content'   => $data['content'],
            'is_admin'  => $user->isSuperAdmin(),
        ]);
    }
}
