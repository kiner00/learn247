<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GlobalAnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $sender,
        public readonly string $announcementSubject,
        public readonly string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[Curzzo] {$this->announcementSubject}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.global-announcement');
    }
}
