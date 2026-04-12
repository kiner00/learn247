<?php

namespace App\Services\Email;

use App\Contracts\EmailProvider;
use App\Models\Community;

class EmailProviderFactory
{
    /** @internal Override for testing only. */
    public static ?EmailProvider $fakeProvider = null;

    public const PROVIDERS = [
        'resend'   => ResendProvider::class,
        'sendgrid' => SendGridProvider::class,
        'postmark' => PostmarkProvider::class,
        'ses'      => SesProvider::class,
        'mailgun'  => MailgunProvider::class,
    ];

    /**
     * Resolve the email provider for a community.
     *
     * @throws \RuntimeException if no provider is configured
     */
    public static function make(Community $community): EmailProvider
    {
        if (static::$fakeProvider) {
            return static::$fakeProvider;
        }

        $providerId = $community->email_provider ?? 'resend';

        $class = self::PROVIDERS[$providerId] ?? null;

        if (! $class) {
            throw new \RuntimeException("Unknown email provider: {$providerId}");
        }

        return new $class();
    }

    /**
     * Get all available providers for the UI dropdown.
     *
     * @return array<array{id: string, label: string}>
     */
    public static function all(): array
    {
        return [
            ['id' => 'resend',   'label' => 'Resend',     'help' => 'Modern email API. Free tier: 3,000 emails/month. resend.com'],
            ['id' => 'sendgrid', 'label' => 'SendGrid',   'help' => 'Twilio SendGrid. Free tier: 100 emails/day. sendgrid.com'],
            ['id' => 'postmark', 'label' => 'Postmark',   'help' => 'Best deliverability. Free tier: 100 emails/month. postmarkapp.com'],
            ['id' => 'ses',      'label' => 'Amazon SES',  'help' => 'Cheapest at scale. $0.10 per 1,000 emails. Key format: ACCESS_KEY:SECRET:REGION'],
            ['id' => 'mailgun',  'label' => 'Mailgun',    'help' => 'Developer-friendly. Free tier: 100 emails/day. mailgun.com'],
        ];
    }
}
