<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly User $user) {}

    public function envelope(): Envelope
    {
        $rendered = EmailTemplate::render('password-reminder', $this->vars());
        $subject = $rendered ? $rendered['subject'] : 'Reminder: Please change your temporary password';

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $rendered = EmailTemplate::render('password-reminder', $this->vars());
        if ($rendered) {
            return new Content(htmlString: $rendered['html']);
        }
        return new Content(view: 'emails.password-reminder');
    }

    private function vars(): array
    {
        return [
            'user_name' => $this->user->name,
            'login_url' => config('app.url') . '/login',
        ];
    }
}
