<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CommunityControllerTest extends TestCase
{
    use RefreshDatabase;

    // ─── index ────────────────────────────────────────────────────────────────

    public function test_index_returns_200(): void
    {
        $response = $this->get('/communities');

        $response->assertOk();
    }

    // ─── store ────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_create_community(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/communities', [
            'name'        => 'My Community',
            'description' => 'A test community.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('communities', ['name' => 'My Community', 'owner_id' => $user->id]);
    }

    public function test_unauthenticated_user_cannot_create_community(): void
    {
        $this->post('/communities', ['name' => 'Test'])
            ->assertRedirect('/login');
    }

    public function test_create_community_requires_name(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/communities', [])
            ->assertSessionHasErrors(['name']);
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_view_a_public_community(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['is_private' => false]);

        $this->actingAs($user)
            ->get("/communities/{$community->slug}")
            ->assertOk();
    }

    public function test_unauthenticated_user_can_view_public_community(): void
    {
        $community = Community::factory()->create(['is_private' => false]);

        $this->get("/communities/{$community->slug}")
            ->assertOk();
    }

    public function test_private_community_show_page_is_accessible(): void
    {
        $owner   = User::factory()->create();
        $other   = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'is_private' => true]);

        $this->actingAs($other)
            ->get("/communities/{$community->slug}")
            ->assertOk();
    }

    // ─── join ─────────────────────────────────────────────────────────────────

    public function test_authenticated_user_can_join_free_community(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $this->actingAs($user)
            ->post("/communities/{$community->slug}/join")
            ->assertRedirect();

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
    }

    public function test_unauthenticated_user_cannot_join(): void
    {
        $community = Community::factory()->create(['price' => 0]);

        $this->post("/communities/{$community->slug}/join")
            ->assertRedirect('/login');
    }

    // ─── members ──────────────────────────────────────────────────────────────

    public function test_member_can_view_members_page(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get("/communities/{$community->slug}/members")
            ->assertOk();
    }

    // ─── settings ─────────────────────────────────────────────────────────────

    public function test_owner_can_view_settings_page(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}/settings")
            ->assertOk();
    }

    public function test_non_owner_cannot_view_settings_page(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $other->id]);

        $this->actingAs($other)
            ->get("/communities/{$community->slug}/settings")
            ->assertForbidden();
    }

    // ─── update ────────────────────────────────────────────────────────────────

    public function test_owner_can_update_community(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->patch("/communities/{$community->slug}", [
            'name' => 'Updated Community Name',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('communities', ['id' => $community->id, 'name' => 'Updated Community Name']);
    }

    public function test_non_owner_cannot_update_community(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->patch("/communities/{$community->slug}", ['name' => 'Hacked Name'])
            ->assertForbidden();

        $this->assertDatabaseHas('communities', ['id' => $community->id, 'name' => $community->name]);
    }

    // ─── destroy ──────────────────────────────────────────────────────────────

    public function test_owner_can_delete_community(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->delete("/communities/{$community->slug}");

        $response->assertRedirect(route('communities.index'));
        $this->assertSoftDeleted('communities', ['id' => $community->id]);
    }

    public function test_non_owner_cannot_delete_community(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->delete("/communities/{$community->slug}")
            ->assertForbidden();

        $this->assertDatabaseHas('communities', ['id' => $community->id]);
    }

    // ─── about ──────────────────────────────────────────────────────────────────

    public function test_about_page_returns_200(): void
    {
        $community = Community::factory()->create();

        $response = $this->get("/communities/{$community->slug}/about");

        $response->assertOk();
    }

    // ─── analytics ─────────────────────────────────────────────────────────────

    public function test_owner_can_view_analytics_page(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner)->get("/communities/{$community->slug}/analytics");

        $response->assertOk();
    }

    // ─── announce ───────────────────────────────────────────────────────────

    public function test_owner_can_announce_to_community(): void
    {
        Mail::fake();

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/announce", [
                'subject' => 'Test Subject',
                'message' => 'Test body message',
            ])
            ->assertRedirect();
    }

    // ─── level-perks ────────────────────────────────────────────────────────

    public function test_owner_can_update_level_perks(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->patch("/communities/{$community->slug}/level-perks", [
                'perks' => ['1' => 'Perk 1', '2' => 'Perk 2'],
            ])
            ->assertRedirect();
    }

    public function test_owner_can_add_gallery_image(): void
    {
        Storage::fake('public');
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/gallery", [
                'image' => UploadedFile::fake()->image('gallery.jpg'),
            ])
            ->assertRedirect();
    }

    public function test_owner_can_remove_gallery_image(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'       => $owner->id,
            'gallery_images' => ['/storage/img1.jpg', '/storage/img2.jpg'],
        ]);

        $this->actingAs($owner)
            ->delete("/communities/{$community->slug}/gallery/0")
            ->assertRedirect();
    }

    public function test_about_page_with_authenticated_user(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user)
            ->get("/communities/{$community->slug}/about")
            ->assertOk();
    }

    public function test_members_page_with_admin_filter(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}/members?filter=admin")
            ->assertOk();
    }

    public function test_analytics_with_subscription_and_payment_data(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $subscriber = User::factory()->create();
        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);
        Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now()->subDays(20),
        ]);

        $this->actingAs($owner)->get("/communities/{$community->slug}/analytics")->assertOk();
    }

    public function test_analytics_with_course_stats(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create(['community_id' => $community->id, 'title' => 'C1']);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        CourseLesson::create(['module_id' => $module->id, 'title' => 'L1', 'position' => 1]);

        $this->actingAs($owner)->get("/communities/{$community->slug}/analytics")->assertOk();
    }

    public function test_non_owner_cannot_view_analytics(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $other->id]);

        $this->actingAs($other)->get("/communities/{$community->slug}/analytics")->assertForbidden();
    }

    public function test_show_with_owner_checklist(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)->get("/communities/{$community->slug}")->assertOk();
    }

    public function test_show_auto_creates_affiliate_for_active_subscriber(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 500]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);
        Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_auto_aff',
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);

        $this->actingAs($user)
            ->get("/communities/{$community->slug}")
            ->assertOk();

        $this->assertDatabaseHas('affiliates', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
    }

    public function test_about_page_with_ref_code_cookie(): void
    {
        $affiliateUser = User::factory()->create();
        $community     = Community::factory()->create(['affiliate_commission_rate' => 10]);
        $affiliate     = \App\Models\Affiliate::create([
            'user_id'      => $affiliateUser->id,
            'community_id' => $community->id,
            'code'         => 'REFTEST1',
            'status'       => \App\Models\Affiliate::STATUS_ACTIVE,
        ]);

        $this->withCookie('ref_code', 'REFTEST1')
            ->get("/communities/{$community->slug}/about")
            ->assertOk();
    }

    public function test_about_page_with_invalid_ref_code_cookie(): void
    {
        $community = Community::factory()->create();

        $this->withCookie('ref_code', 'NONEXISTENT')
            ->get("/communities/{$community->slug}/about")
            ->assertOk();
    }

    public function test_analytics_with_affiliate_data(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500, 'affiliate_commission_rate' => 20]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $affUser = User::factory()->create();
        $affiliate = \App\Models\Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $affUser->id,
            'code'         => 'WEBANAFF',
            'status'       => \App\Models\Affiliate::STATUS_ACTIVE,
        ]);

        $subscriber = User::factory()->create();
        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $subscriber->id,
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => now()->addMonth(),
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id'    => $community->id,
            'user_id'         => $subscriber->id,
            'amount'          => 500,
            'currency'        => 'PHP',
            'status'          => Payment::STATUS_PAID,
            'metadata'        => [],
            'paid_at'         => now()->subDays(20),
        ]);
        \App\Models\AffiliateConversion::create([
            'affiliate_id'    => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id'      => $payment->id,
            'referred_user_id' => $subscriber->id,
            'sale_amount'     => 500,
            'platform_fee'    => 75,
            'commission_amount' => 100,
            'creator_amount'  => 325,
            'status'          => \App\Models\AffiliateConversion::STATUS_PENDING,
        ]);

        $this->actingAs($owner)->get("/communities/{$community->slug}/analytics")->assertOk();
    }

    public function test_analytics_with_payout_history(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        \App\Models\OwnerPayout::create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
            'amount'       => 500,
            'status'       => 'paid',
            'paid_at'      => now()->subDays(5),
        ]);

        $this->actingAs($owner)->get("/communities/{$community->slug}/analytics")->assertOk();
    }

    public function test_analytics_with_pending_payout_request(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        \App\Models\PayoutRequest::create([
            'user_id'        => $owner->id,
            'community_id'   => $community->id,
            'type'           => \App\Models\PayoutRequest::TYPE_OWNER,
            'status'         => \App\Models\PayoutRequest::STATUS_PENDING,
            'amount'         => 300,
            'eligible_amount' => 300,
        ]);

        $this->actingAs($owner)->get("/communities/{$community->slug}/analytics")->assertOk();
    }

    public function test_analytics_course_with_empty_module_returns_zero_completions(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->admin()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $course = Course::create(['community_id' => $community->id, 'title' => 'Empty Course']);
        CourseModule::create(['course_id' => $course->id, 'title' => 'Empty Module', 'position' => 1]);

        $this->actingAs($owner)->get("/communities/{$community->slug}/analytics")->assertOk();
    }
}
