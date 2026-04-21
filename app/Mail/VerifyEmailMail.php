<?php

namespace App\Mail;

use App\Models\User;
use App\Support\EmailVerificationToken;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class VerifyEmailMail extends Mailable
{
    use Queueable, SerializesModels;

    public readonly string $token;

    public readonly string $verifyUrl;

    public function __construct(public readonly User $user)
    {
        $this->token = EmailVerificationToken::issue($user);
        $this->verifyUrl = rtrim(config('app.url'), '/').'/email/verify?token='.urlencode($this->token);
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Verify your email address');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.verify-email',
            with: [
                'user' => $this->user,
                'verifyUrl' => $this->verifyUrl,
                'token' => $this->token,
            ],
        );
    }
}
