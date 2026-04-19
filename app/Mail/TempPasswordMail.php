<?php

namespace App\Mail;

use App\Models\Community;
use App\Models\EmailTemplate;
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
        $rendered = EmailTemplate::render('welcome', $this->vars());
        $subject = $rendered
            ? $rendered['subject']
            : "You're in! Here's your login for {$this->community->name}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $rendered = EmailTemplate::render('welcome', $this->vars());
        if ($rendered) {
            return new Content(htmlString: $rendered['html']);
        }

        return new Content(view: 'emails.temp-password');
    }

    private function vars(): array
    {
        return [
            'community_name' => $this->community->name,
            'user_name' => $this->user->name,
            'user_email' => $this->user->email,
            'temp_password' => $this->tempPassword,
            'login_url' => config('app.url').'/login',
        ];
    }
}
