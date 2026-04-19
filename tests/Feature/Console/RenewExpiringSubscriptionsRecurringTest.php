<?php

namespace Tests\Feature\Console;

use App\Actions\Billing\CreateRenewalInvoice;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

class RenewExpiringSubscriptionsRecurringTest extends TestCase
{
    use RefreshDatabase;

    public function test_skips_recurring_subscriptions(): void
    {
        Mail::fake();
        config(['services.xendit.secret_key' => 'test', 'services.xendit.callback_token' => 'test']);

        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();

        // Recurring subscription expiring in 5 days — should be SKIPPED
        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_plan_id' => 'repl_skip_renew',
            'recurring_status' => 'ACTIVE',
            'expires_at' => now()->addDays(5),
        ]);

        // Mock the action to ensure it's never called
        $invoiceAction = Mockery::mock(CreateRenewalInvoice::class);
        $invoiceAction->shouldNotReceive('execute');
        $this->app->instance(CreateRenewalInvoice::class, $invoiceAction);

        $this->artisan('subscriptions:renew')
            ->assertExitCode(0);
    }

    public function test_still_sends_reminders_for_invoice_based_subscriptions(): void
    {
        Mail::fake();
        config(['services.xendit.secret_key' => 'test', 'services.xendit.callback_token' => 'test']);

        $user = User::factory()->create();
        $community = Community::factory()->paid()->create();

        // Invoice-based subscription expiring in 5 days — should get a reminder
        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'xendit_id' => 'inv_renew_legacy',
            'expires_at' => now()->addDays(5),
        ]);

        $invoiceAction = Mockery::mock(CreateRenewalInvoice::class);
        $invoiceAction->shouldReceive('execute')->once()->andReturn('https://checkout.xendit.co/test');
        $this->app->instance(CreateRenewalInvoice::class, $invoiceAction);

        $this->artisan('subscriptions:renew')
            ->assertExitCode(0);

        Mail::assertQueued(\App\Mail\SubscriptionRenewalReminder::class);
    }
}
