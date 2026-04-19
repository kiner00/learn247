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

class CreatorChaChing extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User $creator,
        public readonly Community $community,
        public readonly float $saleAmount,
        public readonly ?string $referredByName,
    ) {}

    public function envelope(): Envelope
    {
        $rendered = EmailTemplate::render('creator-cha-ching', $this->vars());
        if ($rendered) {
            $subject = $rendered['subject'];
        } else {
            $subject = "💰 Cha-ching! New sale in {$this->community->name}";
        }

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $rendered = EmailTemplate::render('creator-cha-ching', $this->vars());
        if ($rendered) {
            return new Content(htmlString: $rendered['html']);
        }

        return new Content(view: 'emails.creator-cha-ching');
    }

    private function vars(): array
    {
        $referredBy = $this->referredByName
            ? " referred by {$this->referredByName}"
            : '';

        return [
            'creator_name' => $this->creator->name,
            'community_name' => $this->community->name,
            'sale_amount' => number_format($this->saleAmount, 2),
            'referred_by' => $referredBy,
            'dashboard_url' => config('app.url').'/creator/dashboard',
        ];
    }
}
