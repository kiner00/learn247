<?php

namespace Tests\Feature\Api;

use App\Models\Affiliate;
use App\Models\AffiliateConversion;
use App\Models\Community;
use App\Models\CommunityLevelPerk;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\Payment;
use App\Models\PayoutRequest;
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

    // ─── index (public) ──────────────────────────────────────────────────────

    public function test_index_returns_communities(): void
    {
        Community::factory()->count(2)->create();

        $response = $this->getJson('/api/communities');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    // ─── show ─────────────────────────────────────────────────────────────────

    public function test_show_returns_community_with_membership_and_access(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/communities/{$community->slug}");

        $response->assertOk()
            ->assertJsonStructure(['community', 'membership', 'has_access'])
            ->assertJsonPath('has_access', true);
    }

    public function test_show_public_community_without_auth(): void
    {
        $community = Community::factory()->create(['is_private' => false]);

        $response = $this->getJson("/api/communities/{$community->slug}");

        $response->assertOk()
            ->assertJsonStructure(['community', 'membership', 'has_access']);
    }

    // ─── store (authenticated) ───────────────────────────────────────────────

    public function test_store_creates_community_returns_201(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->post('/api/communities', [
                'name'        => 'My New Community',
                'description' => 'A test community description.',
                'cover_image' => UploadedFile::fake()->image('cover.jpg'),
            ], ['Accept' => 'application/json']);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Community created.')
            ->assertJsonStructure(['community']);

        $this->assertDatabaseHas('communities', [
            'name'     => 'My New Community',
            'owner_id' => $user->id,
        ]);
    }

    public function test_store_validates_name_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/communities', ['description' => 'No name provided.']);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    // ─── update (owner only) ──────────────────────────────────────────────────

    public function test_update_by_owner_returns_200(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/communities/{$community->slug}", [
                'name'        => 'Updated Community Name',
                'description' => 'Updated description.',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Community updated.');

        $this->assertDatabaseHas('communities', [
            'id'          => $community->id,
            'name'        => 'Updated Community Name',
            'description' => 'Updated description.',
        ]);
    }

    public function test_update_by_non_owner_returns_403(): void
    {
        $owner     = User::factory()->create();
        $nonOwner  = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($nonOwner, 'sanctum')
            ->patchJson("/api/communities/{$community->slug}", [
                'name'        => 'Hacked Name',
                'description' => 'Should fail.',
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('communities', ['id' => $community->id, 'name' => 'Hacked Name']);
    }

    // ─── destroy (owner only) ─────────────────────────────────────────────────

    public function test_destroy_by_owner_deletes_community(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/communities/{$community->slug}");

        $response->assertOk()
            ->assertJsonPath('message', 'Community deleted.');

        $this->assertSoftDeleted('communities', ['id' => $community->id]);
    }

    public function test_destroy_by_non_owner_returns_403(): void
    {
        $owner     = User::factory()->create();
        $nonOwner  = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($nonOwner, 'sanctum')
            ->deleteJson("/api/communities/{$community->slug}");

        $response->assertForbidden();
        $this->assertDatabaseHas('communities', ['id' => $community->id]);
    }

    // ─── join (authenticated) ──────────────────────────────────────────────────

    public function test_join_free_community(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/join");

        $response->assertStatus(201)
            ->assertJsonPath('message', 'You have joined the community!');

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id'      => $user->id,
        ]);
    }

    // ─── about (public) ──────────────────────────────────────────────────────

    public function test_about_returns_community_info(): void
    {
        $community = Community::factory()->create();

        $response = $this->getJson("/api/communities/{$community->slug}/about");

        $response->assertOk()
            ->assertJsonStructure(['community', 'recent_members', 'gallery']);
    }

    // ─── members (authenticated) ──────────────────────────────────────────────

    public function test_members_returns_paginated_list(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/members");

        $response->assertOk()
            ->assertJsonStructure(['members', 'total_count', 'admin_count']);
    }

    // ─── settings (owner only) ─────────────────────────────────────────────────

    public function test_settings_by_owner_returns_200(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/settings");

        $response->assertOk()
            ->assertJsonStructure(['community', 'pricing_gate', 'level_perks']);
    }

    public function test_settings_by_non_owner_returns_403(): void
    {
        $owner     = User::factory()->create();
        $nonOwner  = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($nonOwner, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/settings");

        $response->assertForbidden();
    }

    // ─── analytics (owner only) ───────────────────────────────────────────────

    public function test_analytics_by_owner_returns_200(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/analytics");

        $response->assertOk()
            ->assertJsonStructure(['stats', 'revenue', 'payout', 'course_stats']);
    }

    public function test_analytics_by_non_owner_returns_403(): void
    {
        $owner     = User::factory()->create();
        $nonOwner  = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($nonOwner, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/analytics");

        $response->assertForbidden();
    }

    public function test_analytics_with_revenue_and_course_data(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        $subscriber = User::factory()->create();
        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'xendit_id' => 'inv_analytics',
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

        $course = Course::create(['community_id' => $community->id, 'title' => 'Course 1']);
        $module = CourseModule::create(['course_id' => $course->id, 'title' => 'M1', 'position' => 1]);
        CourseLesson::create(['module_id' => $module->id, 'title' => 'L1', 'position' => 1]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/analytics")
            ->assertOk()
            ->assertJsonStructure(['stats', 'revenue', 'payout', 'course_stats']);
    }

    public function test_analytics_with_affiliate_conversions(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500, 'affiliate_commission_rate' => 20]);

        $affUser = User::factory()->create();
        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id' => $affUser->id,
            'code' => 'ANALYT01',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $subscriber = User::factory()->create();
        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'affiliate_id' => $affiliate->id,
            'xendit_id' => 'inv_aff_analytics',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);
        $payment = Payment::create([
            'subscription_id' => $sub->id,
            'community_id' => $community->id,
            'user_id' => $subscriber->id,
            'amount' => 500,
            'currency' => 'PHP',
            'status' => Payment::STATUS_PAID,
            'metadata' => [],
            'paid_at' => now()->subDays(20),
        ]);
        AffiliateConversion::create([
            'affiliate_id' => $affiliate->id,
            'subscription_id' => $sub->id,
            'payment_id' => $payment->id,
            'referred_user_id' => $subscriber->id,
            'sale_amount' => 500,
            'platform_fee' => 75,
            'commission_amount' => 100,
            'creator_amount' => 325,
            'status' => AffiliateConversion::STATUS_PENDING,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/analytics")
            ->assertOk()
            ->assertJsonPath('revenue.affiliate_commission', 100);
    }

    public function test_index_with_authenticated_user_flags_membership(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/communities')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_show_paid_community_access_check(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 500]);
        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'xendit_id' => 'inv_access_check',
            'status' => Subscription::STATUS_ACTIVE,
            'expires_at' => now()->addMonth(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/communities/{$community->slug}")
            ->assertOk()
            ->assertJsonPath('has_access', true);
    }

    public function test_show_paid_community_no_subscription_has_no_access(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['price' => 500]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/communities/{$community->slug}")
            ->assertOk()
            ->assertJsonPath('has_access', false);
    }

    public function test_analytics_with_pending_payout_request(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        PayoutRequest::create([
            'user_id' => $owner->id,
            'community_id' => $community->id,
            'type' => PayoutRequest::TYPE_OWNER,
            'status' => PayoutRequest::STATUS_PENDING,
            'amount' => 300,
            'eligible_amount' => 300,
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/analytics")
            ->assertOk()
            ->assertJsonPath('payout.pending_request.amount', '300.00');
    }

    // ─── announce (owner only) ─────────────────────────────────────────────────

    public function test_announce_by_owner_sends_announcement(): void
    {
        Mail::fake();

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $owner->id]);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/announce", [
                'subject' => 'Important Update',
                'message' => 'Hello members, here is an update.',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Announcement sent to 1 members.');
    }

    public function test_announce_validates_required_fields(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/announce", []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['subject', 'message']);
    }

    // ─── updateLevelPerks (owner only) ─────────────────────────────────────────

    public function test_update_level_perks_by_owner(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner, 'sanctum')
            ->patchJson("/api/communities/{$community->slug}/level-perks", [
                'perks' => [
                    1 => 'First perk',
                    2 => 'Second perk',
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Level perks saved.');

        $this->assertDatabaseHas('community_level_perks', [
            'community_id' => $community->id,
            'level'        => 1,
            'description'  => 'First perk',
        ]);
    }

    // ─── leaderboard (authenticated) ───────────────────────────────────────────

    public function test_leaderboard_returns_data(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'     => $user->id,
            'points'      => 50,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/leaderboard");

        $response->assertOk()
            ->assertJsonStructure([
                'my_points',
                'my_level',
                'points_to_next',
                'leaderboard',
                'leaderboard_30_days',
                'leaderboard_7_days',
                'level_perks',
            ]);
    }

    // ─── unauthenticated ──────────────────────────────────────────────────────

    public function test_unauthenticated_users_get_401_on_protected_routes(): void
    {
        $community = Community::factory()->create();

        $this->postJson('/api/communities', ['name' => 'Test'])
            ->assertUnauthorized();

        $this->deleteJson("/api/communities/{$community->slug}")
            ->assertUnauthorized();

        $this->postJson("/api/communities/{$community->slug}/join")
            ->assertUnauthorized();
    }

    public function test_owner_can_add_gallery_image(): void
    {
        Storage::fake('public');
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->post("/api/communities/{$community->slug}/gallery", [
                'image' => UploadedFile::fake()->image('gallery.jpg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();
    }

    public function test_owner_can_remove_gallery_image(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'       => $owner->id,
            'gallery_images' => ['/storage/img1.jpg'],
        ]);

        $this->actingAs($owner, 'sanctum')
            ->deleteJson("/api/communities/{$community->slug}/gallery/0")
            ->assertOk();
    }

    public function test_paid_community_join_returns_error(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 500]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/join")
            ->assertUnprocessable();
    }

    public function test_show_owner_has_access(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/communities/{$community->slug}")
            ->assertOk()
            ->assertJsonPath('has_access', true);
    }

    public function test_show_paid_community_with_null_expires_at_subscription(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 500]);
        Subscription::create([
            'community_id' => $community->id,
            'user_id'      => $user->id,
            'xendit_id'    => 'inv_null_exp',
            'status'       => Subscription::STATUS_ACTIVE,
            'expires_at'   => null,
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/communities/{$community->slug}")
            ->assertOk()
            ->assertJsonPath('has_access', true);
    }

    public function test_show_free_community_member_has_access(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/communities/{$community->slug}")
            ->assertOk()
            ->assertJsonPath('has_access', true)
            ->assertJsonPath('membership.role', 'member');
    }

    public function test_analytics_course_with_empty_module_returns_zero_completions(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $course = Course::create(['community_id' => $community->id, 'title' => 'Empty Course']);
        CourseModule::create(['course_id' => $course->id, 'title' => 'Empty Module', 'position' => 1]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/analytics")
            ->assertOk()
            ->assertJsonPath('course_stats.0.completed_members', 0);
    }

    public function test_show_free_community_non_member_no_access(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/communities/{$community->slug}")
            ->assertOk()
            ->assertJsonPath('has_access', false)
            ->assertJsonPath('membership', null);
    }

    public function test_members_admin_filter_returns_only_admins(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $admin  = User::factory()->create();
        $member = User::factory()->create();

        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $admin->id,
            'role'         => 'admin',
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
            'role'         => 'member',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson("/api/communities/{$community->slug}/members?filter=admin")
            ->assertOk()
            ->assertJsonPath('admin_count', 1);
    }

    public function test_about_with_gallery_images_returns_urls(): void
    {
        Storage::fake('public');

        $community = Community::factory()->create([
            'gallery_images' => ['gallery/img1.jpg', 'gallery/img2.jpg'],
        ]);

        $this->getJson("/api/communities/{$community->slug}/about")
            ->assertOk()
            ->assertJsonCount(2, 'gallery');
    }
}
