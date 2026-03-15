<?php

namespace App\Mail;

use App\Models\Community;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AnnouncementMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Community $community,
        public readonly User $sender,
        public readonly string $announcementSubject,
        public readonly string $message,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "[{$this->community->name}] {$this->announcementSubject}");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.announcement');
    }
}
