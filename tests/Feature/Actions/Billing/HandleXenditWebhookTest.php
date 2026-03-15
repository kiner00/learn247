<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\HandleXenditWebhook;
use App\Mail\TempPasswordMail;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HandleXenditWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function makeRequest(array $body, ?string $callbackToken = null): Request
    {
        $request = Request::create('/xendit/webhook', 'POST', $body);
        if ($callbackToken) {
            $request->headers->set('x-callback-token', $callbackToken);
        }
        return $request;
    }

    public function test_rejects_invalid_callback_token(): void
    {
        config(['services.xendit.callback_token' => 'valid-token']);

        $request = $this->makeRequest(['id' => 'inv_123', 'status' => 'PAID'], 'wrong-token');
        $action = app(HandleXenditWebhook::class);

        $this->expectException(HttpException::class);
        $action->execute($request);
    }

    public function test_processes_paid_invoice_creates_payment_and_membership(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_paid_123',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'       => 'inv_paid_123',
            'status'   => 'PAID',
            'amount'   => 500,
            'currency' => 'PHP',
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status'          => Payment::STATUS_PAID,
            'amount'          => 500,
        ]);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $subscription->id,
            'status' => Subscription::STATUS_ACTIVE,
        ]);

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
    }

    public function test_idempotency_skips_duplicate_events(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_dup_123',
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);

        Payment::create([
            'subscription_id' => $subscription->id,
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'amount'          => 500,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'xendit_event_id' => 'inv_dup_123_PAID',
            'metadata'        => [],
            'paid_at'         => now(),
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_dup_123',
            'status' => 'PAID',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertEquals(1, Payment::where('xendit_event_id', 'inv_dup_123_PAID')->count());
    }

    public function test_skips_non_invoice_events(): void
    {
        config(['services.xendit.callback_token' => 'valid-token']);

        $request = $this->makeRequest([
            'event' => 'disbursement.completed',
            'data'  => ['id' => 'dis_123', 'status' => 'COMPLETED'],
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertEquals(0, Payment::count());
    }

    public function test_no_matching_subscription_is_skipped(): void
    {
        config(['services.xendit.callback_token' => 'valid-token']);

        $request = $this->makeRequest([
            'id'     => 'inv_nonexistent',
            'status' => 'PAID',
            'amount' => 100,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertEquals(0, Payment::count());
    }

    public function test_auto_creates_affiliate_for_paid_subscriber(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create(['needs_password_setup' => false]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_aff_auto',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_aff_auto',
            'status' => 'PAID',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertDatabaseHas('affiliates', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);
    }

    public function test_failed_status_cancels_subscription(): void
    {
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create();
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_fail_123',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_fail_123',
            'status' => 'FAILED',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        $this->assertDatabaseHas('subscriptions', [
            'id'     => $subscription->id,
            'status' => Subscription::STATUS_CANCELLED,
        ]);

        $this->assertDatabaseHas('payments', [
            'subscription_id' => $subscription->id,
            'status'          => Payment::STATUS_FAILED,
        ]);
    }

    public function test_guest_user_receives_temp_password_email(): void
    {
        Mail::fake();
        config(['services.xendit.callback_token' => 'valid-token', 'services.xendit.secret_key' => 'test']);

        $user = User::factory()->create(['needs_password_setup' => true]);
        $community = Community::factory()->create();
        $subscription = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_guest_123',
            'status'       => Subscription::STATUS_PENDING,
            'expires_at'   => null,
        ]);

        $request = $this->makeRequest([
            'id'     => 'inv_guest_123',
            'status' => 'PAID',
            'amount' => 500,
        ], 'valid-token');

        $action = app(HandleXenditWebhook::class);
        $action->execute($request);

        Mail::assertSent(TempPasswordMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });
    }
}
