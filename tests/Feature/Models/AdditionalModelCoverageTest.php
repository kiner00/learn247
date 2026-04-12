<?php

namespace Tests\Feature\Models;

use App\Models\ChatbotMessage;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Coupon;
use App\Models\CouponRedemption;
use App\Models\CreatorSubscription;
use App\Models\Curzzo;
use App\Models\CurzzoMessage;
use App\Models\CurzzoPurchase;
use App\Models\CurzzoTopup;
use App\Models\EmailUnsubscribe;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdditionalModelCoverageTest extends TestCase
{
    use RefreshDatabase;

    // =====================================================================
    // Community — untested accessors/methods
    // =====================================================================

    public function test_community_url_with_custom_domain(): void
    {
        $community = new Community();
        $community->custom_domain = 'learn.example.com';

        $this->assertSame('https://learn.example.com', $community->url());
    }

    public function test_community_url_with_subdomain(): void
    {
        config(['app.url' => 'https://curzzo.com']);
        $community = new Community();
        $community->subdomain = 'mysite';
        $community->slug = 'my-community';

        $this->assertSame('https://mysite.curzzo.com', $community->url());
    }

    public function test_community_url_fallback_to_slug(): void
    {
        config(['app.url' => 'https://curzzo.com']);
        $community = new Community();
        $community->subdomain = null;
        $community->custom_domain = null;
        $community->slug = 'my-community';

        $this->assertSame('https://curzzo.com/communities/my-community', $community->url());
    }

    public function test_community_is_pending_deletion_true(): void
    {
        $community = new Community();
        $community->deletion_requested_at = now();

        $this->assertTrue($community->isPendingDeletion());
    }

    public function test_community_is_pending_deletion_false(): void
    {
        $community = new Community();
        $community->deletion_requested_at = null;

        $this->assertFalse($community->isPendingDeletion());
    }

    public function test_community_active_subscribers_count(): void
    {
        $community = Community::factory()->create();
        $user = User::factory()->create();

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);

        // Expired subscription should not count
        Subscription::factory()->expired()->create([
            'community_id' => $community->id,
            'user_id'      => User::factory()->create()->id,
        ]);

        $this->assertSame(1, $community->activeSubscribersCount());
    }

    public function test_community_platform_fee_rate_free_plan(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->assertSame(0.098, $community->platformFeeRate());
    }

    public function test_community_platform_fee_rate_basic_plan(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => 'basic',
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->assertSame(0.049, $community->platformFeeRate());
    }

    public function test_community_platform_fee_rate_pro_plan(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => 'pro',
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->assertSame(0.029, $community->platformFeeRate());
    }

    public function test_community_curzzos_relationship(): void
    {
        $model = new Community();
        $this->assertInstanceOf(HasMany::class, $model->curzzos());
    }

    public function test_community_email_unsubscribes_relationship(): void
    {
        $model = new Community();
        $this->assertInstanceOf(HasMany::class, $model->emailUnsubscribes());
    }

    public function test_community_cart_events_relationship(): void
    {
        $model = new Community();
        $this->assertInstanceOf(HasMany::class, $model->cartEvents());
    }

    // =====================================================================
    // Curzzo — relationships and helpers
    // =====================================================================

    public function test_curzzo_community_relationship(): void
    {
        $model = new Curzzo();
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_curzzo_messages_relationship(): void
    {
        $model = new Curzzo();
        $this->assertInstanceOf(HasMany::class, $model->messages());
    }

    public function test_curzzo_purchases_relationship(): void
    {
        $model = new Curzzo();
        $this->assertInstanceOf(HasMany::class, $model->purchases());
    }

    public function test_curzzo_is_free_when_price_is_null(): void
    {
        $model = new Curzzo();
        $model->price = null;

        $this->assertTrue($model->isFree());
    }

    public function test_curzzo_is_free_when_price_is_zero(): void
    {
        $model = new Curzzo();
        $model->price = 0;

        $this->assertTrue($model->isFree());
    }

    public function test_curzzo_is_not_free_when_price_is_positive(): void
    {
        $model = new Curzzo();
        $model->price = 99.00;

        $this->assertFalse($model->isFree());
    }

    public function test_curzzo_casts_personality_to_array(): void
    {
        $model = new Curzzo();
        $casts = $model->getCasts();
        $this->assertSame('array', $casts['personality']);
    }

    // =====================================================================
    // CurzzoPurchase — relationships and HasRecurringPlan
    // =====================================================================

    public function test_curzzo_purchase_user_relationship(): void
    {
        $model = new CurzzoPurchase();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_curzzo_purchase_curzzo_relationship(): void
    {
        $model = new CurzzoPurchase();
        $this->assertInstanceOf(BelongsTo::class, $model->curzzo());
    }

    public function test_curzzo_purchase_affiliate_relationship(): void
    {
        $model = new CurzzoPurchase();
        $this->assertInstanceOf(BelongsTo::class, $model->affiliate());
    }

    public function test_curzzo_purchase_is_recurring_true(): void
    {
        $model = new CurzzoPurchase();
        $model->xendit_plan_id = 'plan_123';

        $this->assertTrue($model->isRecurring());
    }

    public function test_curzzo_purchase_is_recurring_false(): void
    {
        $model = new CurzzoPurchase();
        $model->xendit_plan_id = null;

        $this->assertFalse($model->isRecurring());
    }

    public function test_curzzo_purchase_is_auto_renewing_true(): void
    {
        $model = new CurzzoPurchase();
        $model->xendit_plan_id = 'plan_123';
        $model->recurring_status = 'ACTIVE';

        $this->assertTrue($model->isAutoRenewing());
    }

    public function test_curzzo_purchase_is_auto_renewing_false_when_not_recurring(): void
    {
        $model = new CurzzoPurchase();
        $model->xendit_plan_id = null;
        $model->recurring_status = 'ACTIVE';

        $this->assertFalse($model->isAutoRenewing());
    }

    public function test_curzzo_purchase_is_auto_renewing_false_when_status_not_active(): void
    {
        $model = new CurzzoPurchase();
        $model->xendit_plan_id = 'plan_123';
        $model->recurring_status = 'PAUSED';

        $this->assertFalse($model->isAutoRenewing());
    }

    // =====================================================================
    // CurzzoTopup — relationships and helpers
    // =====================================================================

    public function test_curzzo_topup_user_relationship(): void
    {
        $model = new CurzzoTopup();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_curzzo_topup_community_relationship(): void
    {
        $model = new CurzzoTopup();
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_curzzo_topup_remaining_messages_with_pack(): void
    {
        $model = new CurzzoTopup();
        $model->messages = 100;
        $model->messages_used = 40;

        $this->assertSame(60, $model->remainingMessages());
    }

    public function test_curzzo_topup_remaining_messages_returns_zero_when_exhausted(): void
    {
        $model = new CurzzoTopup();
        $model->messages = 10;
        $model->messages_used = 15; // over-consumed

        $this->assertSame(0, $model->remainingMessages());
    }

    public function test_curzzo_topup_remaining_messages_unlimited_day_pass(): void
    {
        $model = new CurzzoTopup();
        $model->messages = 0;
        $model->messages_used = 500;

        $this->assertSame(PHP_INT_MAX, $model->remainingMessages());
    }

    public function test_curzzo_topup_is_active_paid_day_pass_future(): void
    {
        $model = new CurzzoTopup();
        $model->status = CurzzoTopup::STATUS_PAID;
        $model->messages = 0;
        $model->messages_used = 0;
        $model->expires_at = now()->addDay();

        $this->assertTrue($model->isActive());
    }

    public function test_curzzo_topup_is_active_paid_day_pass_expired(): void
    {
        $model = new CurzzoTopup();
        $model->status = CurzzoTopup::STATUS_PAID;
        $model->messages = 0;
        $model->messages_used = 0;
        $model->expires_at = now()->subDay();

        $this->assertFalse($model->isActive());
    }

    public function test_curzzo_topup_is_active_paid_message_pack_with_remaining(): void
    {
        $model = new CurzzoTopup();
        $model->status = CurzzoTopup::STATUS_PAID;
        $model->messages = 50;
        $model->messages_used = 10;

        $this->assertTrue($model->isActive());
    }

    public function test_curzzo_topup_is_active_paid_message_pack_exhausted(): void
    {
        $model = new CurzzoTopup();
        $model->status = CurzzoTopup::STATUS_PAID;
        $model->messages = 10;
        $model->messages_used = 10;

        $this->assertFalse($model->isActive());
    }

    public function test_curzzo_topup_is_not_active_when_pending(): void
    {
        $model = new CurzzoTopup();
        $model->status = CurzzoTopup::STATUS_PENDING;
        $model->messages = 50;
        $model->messages_used = 0;

        $this->assertFalse($model->isActive());
    }

    // =====================================================================
    // ChatbotMessage — relationships
    // =====================================================================

    public function test_chatbot_message_community_relationship(): void
    {
        $model = new ChatbotMessage();
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_chatbot_message_user_relationship(): void
    {
        $model = new ChatbotMessage();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_chatbot_message_fillable(): void
    {
        $model = new ChatbotMessage();
        $this->assertSame(
            ['community_id', 'user_id', 'role', 'content', 'conversation_id'],
            $model->getFillable()
        );
    }

    // =====================================================================
    // EmailUnsubscribe — relationships
    // =====================================================================

    public function test_email_unsubscribe_community_relationship(): void
    {
        $model = new EmailUnsubscribe();
        $this->assertInstanceOf(BelongsTo::class, $model->community());
    }

    public function test_email_unsubscribe_user_relationship(): void
    {
        $model = new EmailUnsubscribe();
        $this->assertInstanceOf(BelongsTo::class, $model->user());
    }

    public function test_email_unsubscribe_has_no_timestamps(): void
    {
        $model = new EmailUnsubscribe();
        $this->assertFalse($model->usesTimestamps());
    }

    public function test_email_unsubscribe_casts_unsubscribed_at(): void
    {
        $model = new EmailUnsubscribe();
        $casts = $model->getCasts();
        $this->assertSame('datetime', $casts['unsubscribed_at']);
    }

    // =====================================================================
    // Coupon — relationships and isRedeemable / hasBeenRedeemedBy
    // =====================================================================

    public function test_coupon_redemptions_relationship(): void
    {
        $model = new Coupon();
        $this->assertInstanceOf(HasMany::class, $model->redemptions());
    }

    public function test_coupon_is_redeemable_when_valid(): void
    {
        $model = new Coupon();
        $model->is_active = true;
        $model->times_redeemed = 0;
        $model->max_redemptions = 10;
        $model->expires_at = now()->addWeek();

        $this->assertTrue($model->isRedeemable());
    }

    public function test_coupon_is_not_redeemable_when_inactive(): void
    {
        $model = new Coupon();
        $model->is_active = false;
        $model->times_redeemed = 0;
        $model->max_redemptions = 10;
        $model->expires_at = now()->addWeek();

        $this->assertFalse($model->isRedeemable());
    }

    public function test_coupon_is_not_redeemable_when_fully_redeemed(): void
    {
        $model = new Coupon();
        $model->is_active = true;
        $model->times_redeemed = 10;
        $model->max_redemptions = 10;
        $model->expires_at = now()->addWeek();

        $this->assertFalse($model->isRedeemable());
    }

    public function test_coupon_is_not_redeemable_when_expired(): void
    {
        $model = new Coupon();
        $model->is_active = true;
        $model->times_redeemed = 0;
        $model->max_redemptions = 10;
        $model->expires_at = now()->subDay();

        $this->assertFalse($model->isRedeemable());
    }

    public function test_coupon_is_redeemable_with_null_expiry(): void
    {
        $model = new Coupon();
        $model->is_active = true;
        $model->times_redeemed = 0;
        $model->max_redemptions = 5;
        $model->expires_at = null;

        $this->assertTrue($model->isRedeemable());
    }

    public function test_coupon_has_been_redeemed_by_user(): void
    {
        $user = User::factory()->create();
        $coupon = Coupon::create([
            'code'            => 'TEST10',
            'plan'            => 'basic',
            'duration_months' => 1,
            'max_redemptions' => 10,
            'times_redeemed'  => 1,
            'is_active'       => true,
        ]);

        $creatorSub = CreatorSubscription::create([
            'user_id'    => $user->id,
            'plan'       => 'basic',
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        CouponRedemption::create([
            'coupon_id'               => $coupon->id,
            'user_id'                 => $user->id,
            'creator_subscription_id' => $creatorSub->id,
            'redeemed_at'             => now(),
        ]);

        $this->assertTrue($coupon->hasBeenRedeemedBy($user->id));
    }

    public function test_coupon_has_not_been_redeemed_by_other_user(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $coupon = Coupon::create([
            'code'            => 'TEST20',
            'plan'            => 'pro',
            'duration_months' => 3,
            'max_redemptions' => 10,
            'times_redeemed'  => 1,
            'is_active'       => true,
        ]);

        $creatorSub = CreatorSubscription::create([
            'user_id'    => $otherUser->id,
            'plan'       => 'pro',
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        CouponRedemption::create([
            'coupon_id'               => $coupon->id,
            'user_id'                 => $otherUser->id,
            'creator_subscription_id' => $creatorSub->id,
            'redeemed_at'             => now(),
        ]);

        $this->assertFalse($coupon->hasBeenRedeemedBy($user->id));
    }

    // =====================================================================
    // HasRecurringPlan trait (via Subscription)
    // =====================================================================

    public function test_subscription_is_recurring_true(): void
    {
        $model = new Subscription();
        $model->xendit_plan_id = 'plan_abc';

        $this->assertTrue($model->isRecurring());
    }

    public function test_subscription_is_recurring_false(): void
    {
        $model = new Subscription();
        $model->xendit_plan_id = null;

        $this->assertFalse($model->isRecurring());
    }

    public function test_subscription_is_auto_renewing_true(): void
    {
        $model = new Subscription();
        $model->xendit_plan_id = 'plan_abc';
        $model->recurring_status = 'ACTIVE';

        $this->assertTrue($model->isAutoRenewing());
    }

    public function test_subscription_is_auto_renewing_false_when_paused(): void
    {
        $model = new Subscription();
        $model->xendit_plan_id = 'plan_abc';
        $model->recurring_status = 'PAUSED';

        $this->assertFalse($model->isAutoRenewing());
    }

    public function test_creator_subscription_uses_has_recurring_plan(): void
    {
        $model = new CreatorSubscription();
        $model->xendit_plan_id = 'plan_xyz';
        $model->recurring_status = 'ACTIVE';

        $this->assertTrue($model->isRecurring());
        $this->assertTrue($model->isAutoRenewing());
    }
}
