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
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Your subscription to {$this->subscription->community->name} is expiring soon",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.subscription-renewal');
    }
}
