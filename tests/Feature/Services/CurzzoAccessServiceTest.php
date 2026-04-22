<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Curzzo;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Community\CurzzoAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurzzoAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    private CurzzoAccessService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CurzzoAccessService::class);
    }

    public function test_inclusive_bot_is_accessible_to_any_member_on_free_community(): void
    {
        $community = Community::factory()->create(['price' => 0]);
        $user = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
        $curzzo = Curzzo::factory()->create([
            'community_id' => $community->id,
            'access_type' => 'inclusive',
        ]);

        $context = $this->service->buildContext($user, $community, collect([$curzzo->id]));

        $this->assertTrue($this->service->hasAccess($curzzo, $context));
    }

    public function test_inclusive_bot_requires_active_subscription_on_paid_community(): void
    {
        $community = Community::factory()->create(['price' => 499]);
        $user = User::factory()->create();
        // Free member row (e.g. trial record without an active subscription) — must NOT grant access.
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);
        $curzzo = Curzzo::factory()->create([
            'community_id' => $community->id,
            'access_type' => 'inclusive',
        ]);

        $context = $this->service->buildContext($user, $community, collect([$curzzo->id]));

        $this->assertFalse($this->service->hasAccess($curzzo, $context));

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id' => $user->id,
        ]);

        $contextAfter = $this->service->buildContext($user, $community, collect([$curzzo->id]));
        $this->assertTrue($this->service->hasAccess($curzzo, $contextAfter));
    }

    public function test_inclusive_bot_locks_out_non_members_on_free_community(): void
    {
        $community = Community::factory()->create(['price' => 0]);
        $user = User::factory()->create();
        $curzzo = Curzzo::factory()->create([
            'community_id' => $community->id,
            'access_type' => 'inclusive',
        ]);

        $context = $this->service->buildContext($user, $community, collect([$curzzo->id]));

        $this->assertFalse($this->service->hasAccess($curzzo, $context));
    }
}
