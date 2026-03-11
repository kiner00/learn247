<?php

namespace App\Mail;

use App\Models\CommunityInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommunityInviteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CommunityInvite $invite) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            subject: "You're invited to join {$this->invite->community->name}",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.community-invite');
    }
}
