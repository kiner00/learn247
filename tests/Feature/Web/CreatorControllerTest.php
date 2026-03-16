<?php

namespace Tests\Feature\Web;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\OwnerPayout;
use App\Models\Payment;
use App\Models\PayoutRequest;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatorControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createPaidPayment(Community $community, User $payer, float $amount, ?string $paidAt = null): Payment
    {
        $subscription = Subscription::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $payer->id,
            'status'       => Subscription::STATUS_ACTIVE,
        ]);

        return Payment::factory()->create([
            'subscription_id' => $subscription->id,
            'community_id'    => $community->id,
            'user_id'         => $payer->id,
            'amount'          => $amount,
            'status'          => Payment::STATUS_PAID,
            'paid_at'         => $paidAt ?? now()->subDays(20),
        ]);
    }

    private function createPayoutRequest(User $owner, Community $community, float $amount, string $status = PayoutRequest::STATUS_PENDING): PayoutRequest
    {
        return PayoutRequest::create([
            'user_id'         => $owner->id,
            'community_id'    => $community->id,
            'type'            => PayoutRequest::TYPE_OWNER,
            'status'          => $status,
            'amount'          => $amount,
            'eligible_amount' => $amount,
        ]);
    }

    // ─── dashboard ─────────────────────────────────────────────────────────────

    public function test_owner_can_view_creator_dashboard(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price'    => 100,
        ]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('communities')
            ->has('requestHistory')
            ->has('payoutMethod')
            ->has('payoutDetails')
        );
    }

    public function test_dashboard_shows_community_revenue_data(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price'    => 200,
        ]);

        $payer = User::factory()->create();
        $this->createPaidPayment($community, $payer, 200.00);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('communities', 1)
            ->where('communities.0.community_id', $community->id)
            ->where('communities.0.community_name', $community->name)
            ->where('communities.0.gross', fn ($v) => (float) $v === 200.0)
            ->where('communities.0.platform_fee', fn ($v) => (float) $v === 30.0)
        );
    }

    public function test_dashboard_includes_affiliate_commissions_in_calculations(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price'    => 100,
        ]);

        $payer   = User::factory()->create();
        $payment = $this->createPaidPayment($community, $payer, 100.00);

        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => User::factory()->create()->id,
            'code'         => 'TEST123',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        AffiliateConversion::create([
            'affiliate_id'      => $affiliate->id,
            'subscription_id'   => $payment->subscription_id,
            'referred_user_id'  => $payer->id,
            'commission_amount' => 10.00,
            'sale_amount'       => 100.00,
            'platform_fee'      => 15.00,
            'creator_amount'    => 75.00,
        ]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->where('communities.0.commissions', fn ($v) => (float) $v === 10.0)
            ->where('communities.0.earned', fn ($v) => (float) $v === 75.0)
        );
    }

    public function test_dashboard_includes_paid_and_eligible_amounts(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price'    => 100,
        ]);

        $payer = User::factory()->create();
        $this->createPaidPayment($community, $payer, 100.00);

        OwnerPayout::create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'amount'       => 50.00,
            'status'       => 'completed',
        ]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->where('communities.0.paid', fn ($v) => (float) $v === 50.0)
        );
    }

    public function test_dashboard_includes_pending_payout_request(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price'    => 100,
        ]);

        $request = $this->createPayoutRequest($owner, $community, 25.00);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->where('communities.0.pending_request.id', $request->id)
            ->where('communities.0.pending_request.amount', fn ($v) => (float) $v === 25.0)
        );
    }

    public function test_dashboard_includes_recent_payments(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price'    => 50,
        ]);

        $payer = User::factory()->create(['name' => 'Test Payer']);
        $this->createPaidPayment($community, $payer, 50.00, now()->subDay());

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('communities.0.recent_payments', 1)
        );
    }

    public function test_dashboard_includes_request_history(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price'    => 100,
        ]);

        $this->createPayoutRequest($owner, $community, 40.00, PayoutRequest::STATUS_APPROVED);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('requestHistory', 1)
        );
    }

    public function test_dashboard_excludes_free_communities(): void
    {
        $owner = User::factory()->create();
        Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('communities', 0)
        );
    }

    public function test_user_with_no_communities_sees_empty_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('communities', 0)
            ->has('requestHistory', 0)
        );
    }

    public function test_owner_does_not_see_other_owners_communities(): void
    {
        $owner      = User::factory()->create();
        $otherOwner = User::factory()->create();

        Community::factory()->create(['owner_id' => $owner->id, 'price' => 100]);
        Community::factory()->create(['owner_id' => $otherOwner->id, 'price' => 100]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->has('communities', 1)
        );
    }

    public function test_dashboard_shows_payout_method_and_details(): void
    {
        $owner = User::factory()->create([
            'payout_method'  => 'bank_transfer',
            'payout_details' => 'BCA 1234567890',
        ]);
        Community::factory()->create(['owner_id' => $owner->id, 'price' => 100]);

        $response = $this->actingAs($owner)->get('/creator/dashboard');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Creator/Dashboard')
            ->where('payoutMethod', 'bank_transfer')
            ->where('payoutDetails', 'BCA 1234567890')
        );
    }

    public function test_guest_cannot_access_creator_dashboard(): void
    {
        $response = $this->get('/creator/dashboard');

        $response->assertRedirect('/login');
    }
}
