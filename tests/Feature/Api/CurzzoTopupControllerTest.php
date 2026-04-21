<?php

namespace Tests\Feature\Api;

use App\Actions\Billing\StartCurzzoTopupCheckout;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\CurzzoTopup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CurzzoTopupControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_packs_returns_default_packs(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/curzzos/topup-packs")
            ->assertOk()
            ->assertJsonStructure([
                'packs' => [
                    ['messages', 'price', 'label'],
                ],
            ]);
    }

    public function test_packs_requires_auth(): void
    {
        $community = Community::factory()->create();

        $this->getJson("/api/communities/{$community->slug}/curzzos/topup-packs")
            ->assertUnauthorized();
    }

    public function test_checkout_validates_pack_index(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/curzzos/topup/checkout", [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('pack_index');
    }

    public function test_checkout_rejects_invalid_pack_index(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/curzzos/topup/checkout", [
                'pack_index' => 999,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Invalid pack selected.');
    }

    public function test_checkout_returns_url_via_action(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $member = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $member->id,
        ]);

        $topup = new CurzzoTopup(['id' => 42]);
        $topup->id = 42;

        $action = Mockery::mock(StartCurzzoTopupCheckout::class);
        $action->shouldReceive('execute')->once()->andReturn([
            'topup' => $topup,
            'checkout_url' => 'https://xendit.test/invoice/abc',
        ]);
        $this->instance(StartCurzzoTopupCheckout::class, $action);

        $this->actingAs($member, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/curzzos/topup/checkout", [
                'pack_index' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('topup_id', 42)
            ->assertJsonPath('checkout_url', 'https://xendit.test/invoice/abc');
    }
}
