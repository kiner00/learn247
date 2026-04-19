<?php

namespace Tests\Unit\Models;

use App\Models\Community;
use Tests\TestCase;

class CommunityTest extends TestCase
{
    public function test_is_free_returns_true_when_price_is_zero(): void
    {
        $community = new Community;
        $community->price = 0;

        $this->assertTrue($community->isFree());
    }

    public function test_is_free_returns_true_when_price_is_negative(): void
    {
        $community = new Community;
        $community->price = -1;

        $this->assertTrue($community->isFree());
    }

    public function test_is_free_returns_false_when_price_is_positive(): void
    {
        $community = new Community;
        $community->price = 499;

        $this->assertFalse($community->isFree());
    }

    public function test_route_key_name_is_slug(): void
    {
        $community = new Community;

        $this->assertSame('slug', $community->getRouteKeyName());
    }

    // ─── hasAffiliateProgram ──────────────────────────────────────────────

    public function test_has_affiliate_program_returns_true_when_rate_is_positive(): void
    {
        $community = new Community;
        $community->affiliate_commission_rate = 10;

        $this->assertTrue($community->hasAffiliateProgram());
    }

    public function test_has_affiliate_program_returns_false_when_rate_is_null(): void
    {
        $community = new Community;
        $community->affiliate_commission_rate = null;

        $this->assertFalse($community->hasAffiliateProgram());
    }

    public function test_has_affiliate_program_returns_false_when_rate_is_zero(): void
    {
        $community = new Community;
        $community->affiliate_commission_rate = 0;

        $this->assertFalse($community->hasAffiliateProgram());
    }

    public function test_is_pending_deletion_returns_false_when_null(): void
    {
        $community = new Community;
        $community->deletion_requested_at = null;

        $this->assertFalse($community->isPendingDeletion());
    }

    public function test_is_pending_deletion_returns_true_when_set(): void
    {
        $community = new Community;
        $community->deletion_requested_at = now();

        $this->assertTrue($community->isPendingDeletion());
    }

    public function test_url_uses_custom_domain_when_set(): void
    {
        $community = new Community;
        $community->custom_domain = 'example.com';

        $this->assertSame('https://example.com', $community->url());
    }

    public function test_url_uses_subdomain_when_set(): void
    {
        config(['app.url' => 'https://curzzo.test']);

        $community = new Community;
        $community->subdomain = 'mycom';

        $this->assertSame('https://mycom.curzzo.test', $community->url());
    }

    public function test_url_falls_back_to_path_when_no_domain_or_subdomain(): void
    {
        config(['app.url' => 'https://curzzo.test']);

        $community = new Community;
        $community->slug = 'foo';

        $this->assertSame('https://curzzo.test/communities/foo', $community->url());
    }

    // ─── Relationship definitions (covers HasMany/BelongsTo methods) ──────────

    public function test_relationship_methods_return_correct_relation_types(): void
    {
        $community = new Community;

        // BelongsTo
        $this->assertInstanceOf(\Illuminate\Database\Eloquent\Relations\BelongsTo::class, $community->owner());

        // HasMany
        $hasManyRelations = [
            'members', 'posts', 'comments', 'subscriptions', 'payments',
            'affiliates', 'courses', 'curzzos', 'messages', 'events',
            'invites', 'certifications', 'tags', 'emailCampaigns',
            'emailUnsubscribes', 'emailSequences', 'cartEvents',
        ];

        foreach ($hasManyRelations as $rel) {
            $this->assertInstanceOf(
                \Illuminate\Database\Eloquent\Relations\HasMany::class,
                $community->{$rel}(),
                "Relation {$rel}() should be HasMany"
            );
        }
    }
}
