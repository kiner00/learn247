<?php

namespace App\Mail;

use App\Models\EmailTemplate;
use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionRenewalReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly string $renewalUrl,
        public readonly bool $urgent = false,
    ) {}

    public function envelope(): Envelope
    {
        $key = $this->urgent ? 'subscription-renewal-urgent' : 'subscription-renewal';
        $rendered = EmailTemplate::render($key, $this->vars());

        $subject = $rendered
            ? $rendered['subject']
            : ($this->urgent
                ? "Last chance — your {$this->subscription->community->name} subscription expires tomorrow"
                : "Your subscription to {$this->subscription->community->name} is expiring in 5 days");

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        $key = $this->urgent ? 'subscription-renewal-urgent' : 'subscription-renewal';
        $rendered = EmailTemplate::render($key, $this->vars());
        if ($rendered) {
            return new Content(htmlString: $rendered['html']);
        }
        return new Content(view: 'emails.subscription-renewal');
    }

    private function vars(): array
    {
        return [
            'user_name'      => $this->subscription->user->name,
            'community_name' => $this->subscription->community->name,
            'expiry_date'    => $this->subscription->expires_at->format('F j, Y'),
            'renewal_url'    => $this->renewalUrl,
        ];
    }
}
