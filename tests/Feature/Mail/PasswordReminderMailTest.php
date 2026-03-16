<?php

namespace Tests\Feature\Mail;

use App\Mail\PasswordReminderMail;
use App\Models\EmailTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PasswordReminderMailTest extends TestCase
{
    use RefreshDatabase;

    public function test_envelope_subject_from_seeded_template(): void
    {
        $user = User::factory()->create();

        $mailable = new PasswordReminderMail($user);
        $envelope = $mailable->envelope();

        $this->assertEquals('Reminder: Please change your temporary password', $envelope->subject);
    }

    public function test_envelope_uses_updated_template_subject(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);

        EmailTemplate::where('key', 'password-reminder')->update([
            'subject' => 'Hey {{user_name}}, update your password!',
        ]);

        $mailable = new PasswordReminderMail($user);
        $envelope = $mailable->envelope();

        $this->assertEquals('Hey John Doe, update your password!', $envelope->subject);
    }

    public function test_envelope_falls_back_to_default_subject_without_template(): void
    {
        $user = User::factory()->create();
        EmailTemplate::where('key', 'password-reminder')->delete();

        $mailable = new PasswordReminderMail($user);
        $envelope = $mailable->envelope();

        $this->assertEquals('Reminder: Please change your temporary password', $envelope->subject);
    }

    public function test_content_falls_back_to_blade_view_without_template(): void
    {
        $user = User::factory()->create();
        EmailTemplate::where('key', 'password-reminder')->delete();

        $mailable = new PasswordReminderMail($user);
        $content = $mailable->content();

        $this->assertEquals('emails.password-reminder', $content->view);
    }

    public function test_content_uses_html_string_from_seeded_template(): void
    {
        $user = User::factory()->create(['name' => 'Jane']);

        $mailable = new PasswordReminderMail($user);
        $content = $mailable->content();

        $this->assertNotNull($content->htmlString);
        $this->assertStringContainsString('Jane', $content->htmlString);
    }

    public function test_content_interpolates_login_url(): void
    {
        $user = User::factory()->create();

        EmailTemplate::where('key', 'password-reminder')->update([
            'html_body' => '<a href="{{login_url}}">Login</a>',
        ]);

        $mailable = new PasswordReminderMail($user);
        $content = $mailable->content();

        $expectedUrl = config('app.url') . '/login';
        $this->assertStringContainsString($expectedUrl, $content->htmlString);
    }

    public function test_mailable_holds_user_instance(): void
    {
        $user = User::factory()->create();

        $mailable = new PasswordReminderMail($user);

        $this->assertSame($user->id, $mailable->user->id);
    }
}
