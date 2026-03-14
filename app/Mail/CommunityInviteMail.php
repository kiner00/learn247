<?php

namespace App\Mail;

use App\Models\CommunityInvite;
use App\Models\EmailTemplate;
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
        $rendered = EmailTemplate::render('community-invite', $this->vars());
        $subject = $rendered
            ? $rendered['subject']
            : "You're invited to join {$this->invite->community->name}";

        return new Envelope(
            from: config('mail.from.address'),
            subject: $subject,
        );
    }

    public function content(): Content
    {
        $rendered = EmailTemplate::render('community-invite', $this->vars());
        if ($rendered) {
            return new Content(htmlString: $rendered['html']);
        }
        return new Content(view: 'emails.community-invite');
    }

    private function vars(): array
    {
        return [
            'community_name'        => $this->invite->community->name,
            'community_name_upper'  => strtoupper($this->invite->community->name),
            'community_description' => $this->invite->community->description ?? '',
            'invite_url'            => config('app.url') . '/invite/' . $this->invite->token,
            'invite_email'          => $this->invite->email,
        ];
    }
}
