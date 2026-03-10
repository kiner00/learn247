<?php

namespace App\Mail;

use App\Models\Community;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TempPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $tempPassword,
        public readonly Community $community,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "You're in! Here's your login for {$this->community->name}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.temp-password');
    }
}
