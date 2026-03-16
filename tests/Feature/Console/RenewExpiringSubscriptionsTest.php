<?php

namespace Tests\Feature\Console;

use App\Actions\Billing\CreateRenewalInvoice;
use App\Mail\SubscriptionRenewalReminder;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class RenewExpiringSubscriptionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_returns_success_exit_code(): void
    {
        Mail::fake();

        $this->artisan('subscriptions:renew')
            ->assertExitCode(0);
    }

    public function test_command_sends_5_day_reminder_and_creates_invoice(): void
    {
        Mail::fake();

        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();

        Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addDays(5),
        ]);

        $invoiceAction = Mockery::mock(CreateRenewalInvoice::class);
        $invoiceAction->shouldReceive('execute')->once()->andReturn('https://checkout.xendit.co/renewal-url');
        $this->instance(CreateRenewalInvoice::class, $invoiceAction);

        $this->artisan('subscriptions:renew')
            ->assertExitCode(0);

        Mail::assertSent(SubscriptionRenewalReminder::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_command_sends_1_day_reminder(): void
    {
        Mail::fake();

        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();

        Subscription::factory()->create([
            'community_id'       => $community->id,
            'user_id'            => $user->id,
            'status'             => Subscription::STATUS_ACTIVE,
            'expires_at'         => now()->addDay(),
            'xendit_invoice_url' => 'https://checkout.xendit.co/existing-url',
        ]);

        $invoiceAction = Mockery::mock(CreateRenewalInvoice::class);
        $invoiceAction->shouldReceive('execute')->never();
        $this->instance(CreateRenewalInvoice::class, $invoiceAction);

        $this->artisan('subscriptions:renew')
            ->assertExitCode(0);

        Mail::assertSent(SubscriptionRenewalReminder::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }

    public function test_command_skips_already_reminded_subscriptions(): void
    {
        Mail::fake();

        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();

        Subscription::factory()->create([
            'community_id'       => $community->id,
            'user_id'            => $user->id,
            'status'             => Subscription::STATUS_ACTIVE,
            'expires_at'         => now()->addDays(5),
            'reminder_5d_sent_at' => now()->subDay(),
        ]);

        $invoiceAction = Mockery::mock(CreateRenewalInvoice::class);
        $invoiceAction->shouldNotReceive('execute');
        $this->instance(CreateRenewalInvoice::class, $invoiceAction);

        $this->artisan('subscriptions:renew')
            ->assertExitCode(0);

        Mail::assertNotSent(SubscriptionRenewalReminder::class);
    }

    public function test_command_ignores_expired_subscriptions(): void
    {
        Mail::fake();

        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();

        Subscription::factory()->expired()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        $invoiceAction = Mockery::mock(CreateRenewalInvoice::class);
        $invoiceAction->shouldNotReceive('execute');
        $this->instance(CreateRenewalInvoice::class, $invoiceAction);

        $this->artisan('subscriptions:renew')
            ->assertExitCode(0);

        Mail::assertNotSent(SubscriptionRenewalReminder::class);
    }

    public function test_command_updates_reminder_sent_at_column(): void
    {
        Mail::fake();

        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $subscription = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addDays(5),
        ]);

        $invoiceAction = Mockery::mock(CreateRenewalInvoice::class);
        $invoiceAction->shouldReceive('execute')->once()->andReturn('https://checkout.xendit.co/test');
        $this->instance(CreateRenewalInvoice::class, $invoiceAction);

        $this->artisan('subscriptions:renew')
            ->assertExitCode(0);

        $subscription->refresh();
        $this->assertNotNull($subscription->reminder_5d_sent_at);
    }

    public function test_command_handles_exception_during_reminder(): void
    {
        Mail::fake();

        $user      = User::factory()->create();
        $community = Community::factory()->paid()->create();

        $subscription = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addDays(5),
        ]);

        $invoiceAction = Mockery::mock(CreateRenewalInvoice::class);
        $invoiceAction->shouldReceive('execute')->once()->andThrow(new \RuntimeException('Xendit unavailable'));
        $this->instance(CreateRenewalInvoice::class, $invoiceAction);

        $this->artisan('subscriptions:renew')
            ->expectsOutputToContain("Failed for subscription #{$subscription->id}: Xendit unavailable")
            ->assertExitCode(0);

        Mail::assertNotSent(SubscriptionRenewalReminder::class);

        $subscription->refresh();
        $this->assertNull($subscription->reminder_5d_sent_at);
    }
}
