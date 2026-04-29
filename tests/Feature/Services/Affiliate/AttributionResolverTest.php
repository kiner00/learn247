<?php

namespace Tests\Feature\Services\Affiliate;

use App\Models\Affiliate;
use App\Models\AffiliateAttribution;
use App\Models\Community;
use App\Models\User;
use App\Services\Affiliate\AttributionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttributionResolverTest extends TestCase
{
    use RefreshDatabase;

    private AttributionResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new AttributionResolver;
    }

    public function test_lifetime_off_returns_last_touch_and_records_nothing(): void
    {
        $community = Community::factory()->create(['lifetime_attribution' => false]);
        $referred = User::factory()->create();
        $aff1 = $this->newAffiliate($community);

        $result = $this->resolver->resolve($community, $referred->id, $aff1);

        $this->assertEquals($aff1->id, $result['affiliate']->id);
        $this->assertFalse($result['is_lifetime']);
        $this->assertEquals(0, AffiliateAttribution::count());
    }

    public function test_lifetime_on_first_touch_records_attribution(): void
    {
        $community = Community::factory()->create(['lifetime_attribution' => true]);
        $referred = User::factory()->create();
        $aff1 = $this->newAffiliate($community);

        $result = $this->resolver->resolve($community, $referred->id, $aff1);

        $this->assertEquals($aff1->id, $result['affiliate']->id);
        $this->assertFalse($result['is_lifetime']);
        $this->assertDatabaseHas('affiliate_attributions', [
            'community_id' => $community->id,
            'referred_user_id' => $referred->id,
            'affiliate_id' => $aff1->id,
        ]);
    }

    public function test_lifetime_on_second_touch_pays_first_affiliate(): void
    {
        $community = Community::factory()->create(['lifetime_attribution' => true]);
        $referred = User::factory()->create();
        $aff1 = $this->newAffiliate($community);
        $aff2 = $this->newAffiliate($community);

        $this->resolver->resolve($community, $referred->id, $aff1);
        $result = $this->resolver->resolve($community, $referred->id, $aff2);

        $this->assertEquals($aff1->id, $result['affiliate']->id);
        $this->assertTrue($result['is_lifetime']);
    }

    public function test_lifetime_on_no_last_touch_pays_prior_attribution(): void
    {
        $community = Community::factory()->create(['lifetime_attribution' => true]);
        $referred = User::factory()->create();
        $aff1 = $this->newAffiliate($community);

        $this->resolver->resolve($community, $referred->id, $aff1);
        $result = $this->resolver->resolve($community, $referred->id, null);

        $this->assertEquals($aff1->id, $result['affiliate']->id);
        $this->assertTrue($result['is_lifetime']);
    }

    public function test_no_referred_user_returns_last_touch(): void
    {
        $community = Community::factory()->create(['lifetime_attribution' => true]);
        $aff1 = $this->newAffiliate($community);

        $result = $this->resolver->resolve($community, null, $aff1);

        $this->assertEquals($aff1->id, $result['affiliate']->id);
        $this->assertFalse($result['is_lifetime']);
    }

    private function newAffiliate(Community $community): Affiliate
    {
        return Affiliate::create([
            'user_id' => User::factory()->create()->id,
            'community_id' => $community->id,
            'code' => 'A'.uniqid(),
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
    }
}
