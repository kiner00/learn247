<?php

namespace Tests\Feature\Actions\Auth;

use App\Actions\Auth\IssueGuestPassword;
use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class IssueGuestPasswordTest extends TestCase
{
    use RefreshDatabase;

    private IssueGuestPassword $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new IssueGuestPassword;
    }

    // ─── generate() ──────────────────────────────────────────────────────────

    public function test_generate_returns_temp_password_for_guest_user(): void
    {
        $user = User::factory()->create(['needs_password_setup' => true]);

        $tempPassword = $this->action->generate($user);

        $this->assertNotNull($tempPassword);
        $this->assertStringStartsWith('Tmp@', $tempPassword);

        // Password should be saved and hashable
        $user->refresh();
        $this->assertTrue(Hash::check($tempPassword, $user->password));
    }

    public function test_generate_returns_null_for_non_guest_user(): void
    {
        $user = User::factory()->create(['needs_password_setup' => false]);

        $result = $this->action->generate($user);

        $this->assertNull($result);
    }

    // ─── sendEmail() ─────────────────────────────────────────────────────────

    public function test_send_email_queues_temp_password_mail(): void
    {
        Mail::fake();

        $user = User::factory()->create();
        $community = Community::factory()->create();

        $this->action->sendEmail($user, 'Tmp@ABCdef', $community);

        Mail::assertQueued(\App\Mail\TempPasswordMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_send_email_does_not_throw_on_mail_failure(): void
    {
        Mail::shouldReceive('to')->andThrow(new \Exception('Mail server down'));

        $user = User::factory()->create();
        $community = Community::factory()->create();

        // Should not throw
        $this->action->sendEmail($user, 'Tmp@ABCdef', $community);

        $this->assertTrue(true);
    }

    // ─── execute() ───────────────────────────────────────────────────────────

    public function test_execute_generates_and_sends_email_for_guest(): void
    {
        Mail::fake();

        $user = User::factory()->create(['needs_password_setup' => true]);
        $community = Community::factory()->create();

        $tempPassword = $this->action->execute($user, $community);

        $this->assertNotNull($tempPassword);
        $this->assertStringStartsWith('Tmp@', $tempPassword);

        Mail::assertQueued(\App\Mail\TempPasswordMail::class);
    }

    public function test_execute_returns_null_and_sends_no_email_for_non_guest(): void
    {
        Mail::fake();

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();

        $result = $this->action->execute($user, $community);

        $this->assertNull($result);
        Mail::assertNothingQueued();
    }
}
