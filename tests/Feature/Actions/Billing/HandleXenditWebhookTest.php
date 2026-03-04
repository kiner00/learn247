<?php

namespace Tests\Feature\Actions\Billing;

use App\Actions\Billing\HandleXenditWebhook;
use App\Actions\Billing\SyncMembershipFromSubscription;
use App\Models\Community;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\XenditService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class HandleXenditWebhookTest extends TestCase
{
    use RefreshDatabase;

    private XenditService $xendit;
    private SyncMembershipFromSubscription $sync;
    private HandleXenditWebhook $action;

    protected function setUp(): void
    {
        parent::setUp();

        $this->xendit = Mockery::mock(XenditService::class);
        $this->sync   = new SyncMembershipFromSubscription();
        $this->action = new HandleXenditWebhook($this->xendit, $this->sync);
    }

    private function makeRequest(array $payload, string $token = 'valid_token'): Request
    {
        $request = Request::create('/api/xendit/webhook', 'POST', $payload);
        $request->headers->set('x-callback-token', $token);
        return $request;
    }

    public function test_throws_401_for_invalid_callback_token(): void
    {
        $this->xendit->shouldReceive('verifyCallbackToken')->with('bad_token')->andReturn(false);

        $this->expectException(HttpException::class);
        $this->action->execute($this->makeRequest(['id' => 'inv_123', 'status' => 'PAID'], 'bad_token'));
    }

    public function test_processes_paid_event_and_activates_subscription(): void
    {
        $user         = User::factory()->create();
        $community    = Community::factory()->paid()->create();
        $subscription = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_abc',
            'status'       => Subscription::STATUS_PENDING,
        ]);

        $this->xendit->shouldReceive('verifyCallbackToken')->with('valid_token')->andReturn(true);

        $payload = ['id' => 'inv_abc', 'status' => 'PAID', 'amount' => 499, 'currency' => 'PHP'];
        $this->action->execute($this->makeRequest($payload, 'valid_token'));

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'status' => Subscription::STATUS_ACTIVE]);
        $this->assertDatabaseHas('payments', ['xendit_event_id' => 'inv_abc_PAID', 'status' => Payment::STATUS_PAID]);
    }

    public function test_skips_duplicate_event_idempotency(): void
    {
        $user         = User::factory()->create();
        $community    = Community::factory()->paid()->create();
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_dup',
        ]);
        Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'community_id'    => $community->id,
            'user_id'         => $user->id,
            'xendit_event_id' => 'inv_dup_PAID',
        ]);

        $this->xendit->shouldReceive('verifyCallbackToken')->with('valid_token')->andReturn(true);

        $initialCount = Payment::count();
        $payload      = ['id' => 'inv_dup', 'status' => 'PAID', 'amount' => 499, 'currency' => 'PHP'];
        $this->action->execute($this->makeRequest($payload, 'valid_token'));

        $this->assertEquals($initialCount, Payment::count());
    }

    public function test_ignores_unknown_subscription(): void
    {
        $this->xendit->shouldReceive('verifyCallbackToken')->with('valid_token')->andReturn(true);

        $payload = ['id' => 'inv_unknown', 'status' => 'PAID'];
        // Should not throw, just log and return
        $this->action->execute($this->makeRequest($payload, 'valid_token'));

        $this->assertDatabaseEmpty('payments');
    }

    public function test_processes_expired_event(): void
    {
        $user         = User::factory()->create();
        $community    = Community::factory()->paid()->create();
        $subscription = Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_exp',
        ]);

        $this->xendit->shouldReceive('verifyCallbackToken')->with('valid_token')->andReturn(true);

        $payload = ['id' => 'inv_exp', 'status' => 'EXPIRED', 'amount' => 499, 'currency' => 'PHP'];
        $this->action->execute($this->makeRequest($payload, 'valid_token'));

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'status' => Subscription::STATUS_EXPIRED]);
        $this->assertDatabaseHas('payments', ['xendit_event_id' => 'inv_exp_EXPIRED', 'status' => Payment::STATUS_EXPIRED]);
    }

    public function test_processes_failed_event(): void
    {
        $user         = User::factory()->create();
        $community    = Community::factory()->paid()->create();
        $subscription = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_fail',
            'status'       => Subscription::STATUS_PENDING,
        ]);

        $this->xendit->shouldReceive('verifyCallbackToken')->with('valid_token')->andReturn(true);

        $payload = ['id' => 'inv_fail', 'status' => 'FAILED', 'amount' => 499, 'currency' => 'PHP'];
        $this->action->execute($this->makeRequest($payload, 'valid_token'));

        $this->assertDatabaseHas('subscriptions', ['id' => $subscription->id, 'status' => Subscription::STATUS_CANCELLED]);
    }
}
