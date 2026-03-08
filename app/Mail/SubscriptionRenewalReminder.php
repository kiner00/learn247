<?php

namespace App\Mail;

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
        $subject = $this->urgent
            ? "Last chance — your {$this->subscription->community->name} subscription expires tomorrow"
            : "Your subscription to {$this->subscription->community->name} is expiring in 5 days";

        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subscription-renewal');
    }
}
