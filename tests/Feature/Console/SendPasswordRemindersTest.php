<?php

namespace Tests\Feature\Console;

use App\Mail\PasswordReminderMail;
use App\Mail\TempPasswordMail;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendPasswordRemindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_completes_successfully(): void
    {
        Mail::fake();

        $this->artisan('passwords:send-reminders')
            ->assertExitCode(0);
    }

    public function test_command_sends_day_3_reminder_email(): void
    {
        Mail::fake();

        $user = User::factory()->create([
            'needs_password_setup' => true,
            'created_at'           => now()->subDays(3)->subHours(2),
        ]);

        $this->artisan('passwords:send-reminders')
            ->assertExitCode(0);

        Mail::assertSent(PasswordReminderMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_command_does_not_send_day_3_reminder_for_users_who_set_password(): void
    {
        Mail::fake();

        User::factory()->create([
            'needs_password_setup' => false,
            'created_at'           => now()->subDays(3)->subHours(2),
        ]);

        $this->artisan('passwords:send-reminders')
            ->assertExitCode(0);

        Mail::assertNotSent(PasswordReminderMail::class);
    }

    public function test_command_sends_day_5_temp_password_email(): void
    {
        Mail::fake();

        $user      = User::factory()->create([
            'needs_password_setup' => true,
            'created_at'           => now()->subDays(5)->subHours(2),
        ]);
        $community = Community::factory()->create();

        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);

        $this->artisan('passwords:send-reminders')
            ->assertExitCode(0);

        Mail::assertSent(TempPasswordMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_command_does_not_send_temp_password_without_active_subscription(): void
    {
        Mail::fake();

        User::factory()->create([
            'needs_password_setup' => true,
            'created_at'           => now()->subDays(5)->subHours(2),
        ]);

        $this->artisan('passwords:send-reminders')
            ->assertExitCode(0);

        Mail::assertNotSent(TempPasswordMail::class);
    }

    public function test_command_resets_password_for_day_5_users(): void
    {
        Mail::fake();

        $user      = User::factory()->create([
            'needs_password_setup' => true,
            'created_at'           => now()->subDays(5)->subHours(2),
        ]);
        $community = Community::factory()->create();

        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);

        $originalPassword = $user->password;

        $this->artisan('passwords:send-reminders')
            ->assertExitCode(0);

        $user->refresh();
        $this->assertNotEquals($originalPassword, $user->password);
    }

    public function test_command_outputs_summary(): void
    {
        Mail::fake();

        User::factory()->create([
            'needs_password_setup' => true,
            'created_at'           => now()->subDays(3)->subHours(2),
        ]);

        $this->artisan('passwords:send-reminders')
            ->expectsOutputToContain('Done. Reminders: 1, New temp passwords: 0')
            ->assertExitCode(0);
    }

    public function test_command_skips_users_outside_reminder_window(): void
    {
        Mail::fake();

        User::factory()->create([
            'needs_password_setup' => true,
            'created_at'           => now()->subDays(1),
        ]);

        User::factory()->create([
            'needs_password_setup' => true,
            'created_at'           => now()->subDays(10),
        ]);

        $this->artisan('passwords:send-reminders')
            ->assertExitCode(0);

        Mail::assertNotSent(PasswordReminderMail::class);
        Mail::assertNotSent(TempPasswordMail::class);
    }
}
