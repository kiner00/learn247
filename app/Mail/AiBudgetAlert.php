<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AiBudgetAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $scope,
        public int $scopeId,
        public string $scopeLabel,
        public float $spent,
        public float $threshold,
        public int $windowMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "[Curzzo] AI spend alert — {$this->scope} {$this->scopeLabel} hit \$".number_format($this->spent, 2),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.ai-budget-alert',
        );
    }
}
