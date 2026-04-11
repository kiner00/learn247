<?php

namespace App\Console\Commands;

use App\Mail\TicketResolvedReminderMail;
use App\Models\Ticket;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class RemindResolvedTickets extends Command
{
    protected $signature = 'tickets:remind-resolved';

    protected $description = 'Send reminders to users with resolved tickets that need verification';

    public function handle(): int
    {
        // Only remind tickets resolved 2–3 days ago so each ticket gets one reminder
        $tickets = Ticket::where('status', 'resolved')
            ->whereBetween('updated_at', [now()->subDays(3), now()->subDays(2)])
            ->with('user')
            ->get();

        $count = 0;

        foreach ($tickets as $ticket) {
            if (! $ticket->user?->email) {
                continue;
            }

            Mail::to($ticket->user->email)->queue(new TicketResolvedReminderMail($ticket));
            $count++;
        }

        $this->info("Sent {$count} resolved ticket reminder(s).");

        return self::SUCCESS;
    }
}
