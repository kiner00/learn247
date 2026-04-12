<?php

namespace Tests\Unit\Services\Email;

use App\Contracts\EmailProvider;
use App\Models\Community;
use App\Services\Email\EmailProviderFactory;
use App\Services\Email\MailgunProvider;
use App\Services\Email\PostmarkProvider;
use App\Services\Email\ResendProvider;
use App\Services\Email\SendGridProvider;
use App\Services\Email\SesProvider;
use PHPUnit\Framework\TestCase;

class EmailProviderFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        EmailProviderFactory::$fakeProvider = null;
        parent::tearDown();
    }

    public function test_make_returns_fake_provider_when_set(): void
    {
        $fake = $this->createMock(EmailProvider::class);
        EmailProviderFactory::$fakeProvider = $fake;

        $community = new Community();

        $this->assertSame($fake, EmailProviderFactory::make($community));
    }

    public function test_make_defaults_to_resend_when_no_provider_set(): void
    {
        $community = new Community();

        $provider = EmailProviderFactory::make($community);

        $this->assertInstanceOf(ResendProvider::class, $provider);
    }

    public function test_make_resolves_each_known_provider(): void
    {
        $mapping = [
            'resend'   => ResendProvider::class,
            'sendgrid' => SendGridProvider::class,
            'postmark' => PostmarkProvider::class,
            'ses'      => SesProvider::class,
            'mailgun'  => MailgunProvider::class,
        ];

        foreach ($mapping as $id => $class) {
            $community = new Community();
            $community->email_provider = $id;

            $this->assertInstanceOf($class, EmailProviderFactory::make($community), "Failed for {$id}");
        }
    }

    public function test_make_throws_for_unknown_provider(): void
    {
        $community = new Community();
        $community->email_provider = 'does-not-exist';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unknown email provider: does-not-exist');

        EmailProviderFactory::make($community);
    }

    public function test_all_returns_provider_metadata(): void
    {
        $all = EmailProviderFactory::all();

        $this->assertCount(5, $all);
        $ids = array_column($all, 'id');
        $this->assertContains('resend', $ids);
        $this->assertContains('sendgrid', $ids);
        $this->assertContains('postmark', $ids);
        $this->assertContains('ses', $ids);
        $this->assertContains('mailgun', $ids);

        foreach ($all as $entry) {
            $this->assertArrayHasKey('id', $entry);
            $this->assertArrayHasKey('label', $entry);
            $this->assertArrayHasKey('help', $entry);
        }
    }
}
