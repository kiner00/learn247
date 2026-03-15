<?php

namespace Tests\Feature\Mail;

use App\Mail\TempPasswordMail;
use App\Models\Community;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TempPasswordMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_temp_password_mail_has_correct_default_subject(): void
    {
        $community = Community::factory()->create(['name' => 'My Community']);
        $user = User::factory()->create();

        $mail = new TempPasswordMail($user, 'TmpPass123', $community);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('My Community', $envelope->subject);
    }

    public function test_temp_password_mail_uses_view_when_no_template(): void
    {
        EmailTemplate::where('key', 'welcome')->delete();

        $community = Community::factory()->create();
        $user = User::factory()->create();

        $mail = new TempPasswordMail($user, 'TmpPass123', $community);
        $content = $mail->content();

        $this->assertEquals('emails.temp-password', $content->view);
    }
}
