<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Ticket $ticket,
        public readonly string $oldStatus,
        public readonly string $newStatus,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Ticket #{$this->ticket->id} — Status updated to " . str_replace('_', ' ', ucfirst($this->newStatus)),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.ticket-status-changed');
    }
}
