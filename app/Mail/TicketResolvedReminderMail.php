<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketResolvedReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reminder: Ticket #{$this->ticket->id} is resolved — please verify and close",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ticket-resolved-reminder');
    }
}
