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

    public function test_envelope_uses_custom_template_subject(): void
    {
        EmailTemplate::updateOrCreate(
            ['key' => 'welcome'],
            [
                'name' => 'Welcome Email',
                'subject' => 'Welcome {{user_name}} to {{community_name}}',
                'html_body' => '<p>Hello</p>',
            ]
        );

        $community = Community::factory()->create(['name' => 'TestComm']);
        $user = User::factory()->create(['name' => 'John']);

        $mail = new TempPasswordMail($user, 'Pass123', $community);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('John', $envelope->subject);
        $this->assertStringContainsString('TestComm', $envelope->subject);
    }

    public function test_envelope_uses_default_subject_when_no_template(): void
    {
        EmailTemplate::where('key', 'welcome')->delete();

        $community = Community::factory()->create(['name' => 'Acme Group']);
        $user = User::factory()->create();

        $mail = new TempPasswordMail($user, 'TmpPass123', $community);
        $envelope = $mail->envelope();

        $this->assertStringContainsString('Acme Group', $envelope->subject);
        $this->assertStringContainsString("You're in!", $envelope->subject);
    }

    public function test_content_uses_custom_template_html(): void
    {
        EmailTemplate::updateOrCreate(
            ['key' => 'welcome'],
            [
                'name' => 'Welcome Email',
                'subject' => 'Welcome',
                'html_body' => '<p>Hi {{user_name}}, your password is {{temp_password}}</p>',
            ]
        );

        $community = Community::factory()->create();
        $user = User::factory()->create(['name' => 'Jane']);

        $mail = new TempPasswordMail($user, 'Secret123', $community);
        $content = $mail->content();

        $this->assertNotNull($content->htmlString);
        $this->assertStringContainsString('Jane', $content->htmlString);
        $this->assertStringContainsString('Secret123', $content->htmlString);
    }
}
