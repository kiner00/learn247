<?php

namespace Tests\Feature\Api;

use App\Models\Affiliate;
use App\Models\Community;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AffiliateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_affiliate_stats_for_user(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);
        Affiliate::create([
            'user_id'      => $user->id,
            'community_id' => $community->id,
            'code'         => 'TEST123',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->getJson('/api/affiliates')
            ->assertOk()
            ->assertJsonStructure([
                'affiliates',
                'summary',
                'conversions',
                'period',
            ]);
    }

    public function test_joins_as_affiliate_when_community_has_affiliate_program(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['affiliate_commission_rate' => 15]);
        Subscription::create([
            'community_id' => $community->id,
            'user_id'     => $user->id,
            'status'      => Subscription::STATUS_ACTIVE,
            'expires_at'  => null,
        ]);

        $this->actingAs($user)
            ->postJson("/api/communities/{$community->slug}/affiliates")
            ->assertStatus(201)
            ->assertJsonPath('message', 'You are now an affiliate!');

        $this->assertDatabaseHas('affiliates', [
            'user_id'      => $user->id,
            'community_id' => $community->id,
        ]);
    }

    public function test_updates_payout_method_and_details(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'user_id'      => $user->id,
            'community_id' => $community->id,
            'code'         => 'TEST123',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $this->actingAs($user)
            ->patchJson("/api/affiliates/{$affiliate->id}/payout", [
                'payout_method'  => 'gcash',
                'payout_details' => '09171234567',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Payout details saved.');

        $affiliate->refresh();
        $this->assertSame('gcash', $affiliate->payout_method);
        $this->assertSame('09171234567', $affiliate->payout_details);
    }

    public function test_unauthenticated_returns_401_for_affiliates_index(): void
    {
        $this->getJson('/api/affiliates')
            ->assertUnauthorized();
    }

    public function test_unauthenticated_returns_401_for_join_affiliate(): void
    {
        $community = Community::factory()->create(['affiliate_commission_rate' => 10]);

        $this->postJson("/api/communities/{$community->slug}/affiliates")
            ->assertUnauthorized();
    }

    public function test_unauthenticated_returns_401_for_update_payout(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        $affiliate = Affiliate::create([
            'user_id'      => $user->id,
            'community_id' => $community->id,
            'code'         => 'TEST123',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        $this->patchJson("/api/affiliates/{$affiliate->id}/payout", [
            'payout_method'  => 'gcash',
            'payout_details' => '09171234567',
        ])
            ->assertUnauthorized();
    }
}
