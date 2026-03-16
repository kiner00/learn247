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

class AffiliateChaChing extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly User      $affiliate,
        public readonly Community $community,
        public readonly float     $saleAmount,
        public readonly float     $commissionAmount,
    ) {}

    public function envelope(): Envelope
    {
        $rendered = EmailTemplate::render('affiliate-cha-ching', $this->vars());
        $subject  = $rendered
            ? $rendered['subject']
            : "💰 Cha-ching! You made a sale from {$this->community->name}";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $rendered = EmailTemplate::render('affiliate-cha-ching', $this->vars());
        if ($rendered) {
            return new Content(htmlString: $rendered['html']);
        }

        return new Content(view: 'emails.affiliate-cha-ching');
    }

    private function vars(): array
    {
        return [
            'affiliate_name'    => $this->affiliate->name,
            'community_name'    => $this->community->name,
            'sale_amount'       => number_format($this->saleAmount, 2),
            'commission_amount' => number_format($this->commissionAmount, 2),
        ];
    }
}
