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

    // ─── Trial / promo helpers ────────────────────────────────────────────────

    public function test_has_trial_false_when_community_is_free(): void
    {
        $community = new Community;
        $community->price = 0;
        $community->trial_mode = Community::TRIAL_PER_USER;
        $community->trial_days = 7;

        $this->assertFalse($community->hasTrial());
    }

    public function test_has_trial_true_for_per_user_with_positive_days(): void
    {
        $community = new Community;
        $community->price = 499;
        $community->trial_mode = Community::TRIAL_PER_USER;
        $community->trial_days = 7;

        $this->assertTrue($community->hasTrial());
    }

    public function test_has_trial_false_for_per_user_with_zero_days(): void
    {
        $community = new Community;
        $community->price = 499;
        $community->trial_mode = Community::TRIAL_PER_USER;
        $community->trial_days = 0;

        $this->assertFalse($community->hasTrial());
    }

    public function test_has_trial_true_for_future_window(): void
    {
        $community = new Community;
        $community->price = 499;
        $community->trial_mode = Community::TRIAL_WINDOW;
        $community->free_until = now()->addWeek();

        $this->assertTrue($community->hasTrial());
    }

    public function test_has_trial_false_for_past_window(): void
    {
        $community = new Community;
        $community->price = 499;
        $community->trial_mode = Community::TRIAL_WINDOW;
        $community->free_until = now()->subDay();

        $this->assertFalse($community->hasTrial());
    }

    public function test_trial_expires_at_adds_days_for_per_user(): void
    {
        $community = new Community;
        $community->price = 499;
        $community->trial_mode = Community::TRIAL_PER_USER;
        $community->trial_days = 10;

        $joinedAt = now();
        $expires = $community->trialExpiresAtFor($joinedAt);

        $this->assertNotNull($expires);
        $this->assertEqualsWithDelta($joinedAt->copy()->addDays(10)->timestamp, $expires->timestamp, 1);
    }

    public function test_trial_expires_at_returns_free_until_for_window(): void
    {
        $future = now()->addDays(30);
        $community = new Community;
        $community->price = 499;
        $community->trial_mode = Community::TRIAL_WINDOW;
        $community->free_until = $future;

        $this->assertEqualsWithDelta($future->timestamp, $community->trialExpiresAtFor()->timestamp, 1);
    }

    public function test_trial_expires_at_returns_null_when_no_trial(): void
    {
        $community = new Community;
        $community->price = 499;
        $community->trial_mode = Community::TRIAL_NONE;

        $this->assertNull($community->trialExpiresAtFor());
    }

    public function test_first_charge_amount_uses_promo_when_set(): void
    {
        $community = new Community;
        $community->price = 999;
        $community->first_month_price = 199;

        $this->assertSame(199.0, $community->firstChargeAmount());
    }

    public function test_first_charge_amount_falls_back_to_price(): void
    {
        $community = new Community;
        $community->price = 999;
        $community->first_month_price = null;

        $this->assertSame(999.0, $community->firstChargeAmount());
    }

    public function test_has_promo_first_month_false_when_promo_null(): void
    {
        $community = new Community;
        $community->price = 999;
        $community->first_month_price = null;

        $this->assertFalse($community->hasPromoFirstMonth());
    }

    public function test_has_promo_first_month_false_when_promo_equal_to_price(): void
    {
        $community = new Community;
        $community->price = 999;
        $community->first_month_price = 999;

        $this->assertFalse($community->hasPromoFirstMonth());
    }

    public function test_has_promo_first_month_true_when_promo_lower_than_price(): void
    {
        $community = new Community;
        $community->price = 999;
        $community->first_month_price = 199;

        $this->assertTrue($community->hasPromoFirstMonth());
    }

    public function test_display_price_note_for_free_community(): void
    {
        $community = new Community;
        $community->price = 0;

        $this->assertSame('Free to join', $community->displayPriceNote());
    }

    public function test_display_price_note_with_trial_and_promo(): void
    {
        $community = new Community;
        $community->price = 999;
        $community->currency = 'PHP';
        $community->billing_type = Community::BILLING_MONTHLY;
        $community->trial_mode = Community::TRIAL_PER_USER;
        $community->trial_days = 7;
        $community->first_month_price = 199;

        $this->assertSame('Free for 7 days, then PHP 199 first month, PHP 999/month after', $community->displayPriceNote());
    }

    public function test_display_price_note_for_plain_paid(): void
    {
        $community = new Community;
        $community->price = 999;
        $community->currency = 'PHP';
        $community->billing_type = Community::BILLING_MONTHLY;
        $community->trial_mode = Community::TRIAL_NONE;

        $this->assertSame('PHP 999/month', $community->displayPriceNote());
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
