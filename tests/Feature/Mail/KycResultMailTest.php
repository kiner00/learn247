<?php

namespace Tests\Feature\Mail;

use App\Mail\KycResultMail;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KycResultMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_approved_mail_has_correct_subject(): void
    {
        $user = User::factory()->create();

        $mail = new KycResultMail($user, approved: true);
        $envelope = $mail->envelope();

        $this->assertEquals('Your identity verification has been approved!', $envelope->subject);
    }

    public function test_rejected_mail_has_correct_subject(): void
    {
        $user = User::factory()->create();

        $mail = new KycResultMail($user, approved: false, reason: 'Blurry document');
        $envelope = $mail->envelope();

        $this->assertEquals('Your identity verification was not approved', $envelope->subject);
    }

    public function test_content_uses_correct_view(): void
    {
        $user = User::factory()->create();

        $mail = new KycResultMail($user, approved: true);

        $this->assertEquals('emails.kyc-result', $mail->content()->view);
    }
}
