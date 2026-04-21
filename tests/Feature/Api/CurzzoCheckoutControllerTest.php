<?php

namespace Tests\Feature\Api;

use App\Actions\Billing\StartCurzzoCheckout;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Curzzo;
use App\Models\CurzzoPurchase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CurzzoCheckoutControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_returns_checkout_url(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->paidOnce()->create(['community_id' => $community->id]);

        $member = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $purchase = new CurzzoPurchase(['id' => 7]);
        $purchase->id = 7;

        $action = Mockery::mock(StartCurzzoCheckout::class);
        $action->shouldReceive('execute')->once()->andReturn([
            'purchase' => $purchase,
            'checkout_url' => 'https://xendit.test/invoice/xyz',
        ]);
        $this->instance(StartCurzzoCheckout::class, $action);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/curzzos/{$curzzo->id}/checkout")
            ->assertOk()
            ->assertJsonPath('purchase_id', 7)
            ->assertJsonPath('checkout_url', 'https://xendit.test/invoice/xyz');
    }

    public function test_checkout_404s_for_inactive_curzzo(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzo = Curzzo::factory()->inactive()->paidOnce()->create(['community_id' => $community->id]);

        $member = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/curzzos/{$curzzo->id}/checkout")
            ->assertNotFound();
    }

    public function test_checkout_404s_for_curzzo_from_other_community(): void
    {
        $owner = User::factory()->create();
        $a = Community::factory()->create(['owner_id' => $owner->id]);
        $b = Community::factory()->create(['owner_id' => $owner->id]);
        $curzzoB = Curzzo::factory()->paidOnce()->create(['community_id' => $b->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/communities/{$a->slug}/curzzos/{$curzzoB->id}/checkout")
            ->assertNotFound();
    }

    public function test_checkout_requires_auth(): void
    {
        $community = Community::factory()->create();
        $curzzo = Curzzo::factory()->paidOnce()->create(['community_id' => $community->id]);

        $this->postJson("/api/communities/{$community->slug}/curzzos/{$curzzo->id}/checkout")
            ->assertUnauthorized();
    }
}
