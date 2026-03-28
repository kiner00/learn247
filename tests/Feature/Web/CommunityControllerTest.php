<?php

namespace Tests\Feature\Web;

use App\Actions\Community\GenerateLandingPage;
use App\Actions\Community\RegenerateLandingSection;
use App\Actions\Community\SendAnnouncement;
use App\Contracts\SmsProvider;
use App\Models\Affiliate;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\CourseLesson;
use App\Models\CourseModule;
use App\Models\CreatorSubscription;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
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
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/communities', [
            'name'        => 'My Community',
            'description' => 'A test community.',
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
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

    public function test_deleting_community_with_active_subscribers_schedules_deletion(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->paid()->create(['owner_id' => $owner->id]);
        $subscriber = User::factory()->create();

        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $subscriber->id,
        ]);

        $response = $this->actingAs($owner)->delete("/communities/{$community->slug}");

        $response->assertRedirect();
        $response->assertSessionHas('info');
        $this->assertNotNull($community->fresh()->deletion_requested_at);
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

    // ─── cancelDeletion ───────────────────────────────────────────────────────────

    public function test_owner_can_cancel_scheduled_deletion(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'              => $owner->id,
            'deletion_requested_at' => now()->addDays(7),
        ]);

        $response = $this->actingAs($owner)
            ->post("/communities/{$community->slug}/cancel-deletion");

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertNull($community->fresh()->deletion_requested_at);
    }

    public function test_non_owner_cannot_cancel_scheduled_deletion(): void
    {
        $owner     = User::factory()->create();
        $other     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'              => $owner->id,
            'deletion_requested_at' => now()->addDays(7),
        ]);

        $this->actingAs($other)
            ->post("/communities/{$community->slug}/cancel-deletion")
            ->assertForbidden();

        $this->assertNotNull($community->fresh()->deletion_requested_at);
    }

    // ─── index with filters ──────────────────────────────────────────────────

    public function test_index_with_search_filter(): void
    {
        Community::factory()->create(['name' => 'Laravel Lovers']);

        $this->get('/communities?search=Laravel&category=Tech&sort=popular')
            ->assertOk();
    }

    // ─── store plan limit ────────────────────────────────────────────────────

    public function test_store_fails_when_plan_limit_reached(): void
    {
        $user = User::factory()->create(); // free plan = 1 community max
        Community::factory()->create(['owner_id' => $user->id]);

        $response = $this->actingAs($user)->post('/communities', [
            'name'        => 'Second Community',
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('plan');
    }

    // ─── members with free and paid filters ──────────────────────────────────

    public function test_members_page_with_free_filter(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $owner->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}/members?filter=free")
            ->assertOk();
    }

    public function test_members_page_with_paid_filter(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $owner->id,
            'membership_type' => CommunityMember::MEMBERSHIP_PAID,
        ]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}/members?filter=paid")
            ->assertOk();
    }

    // ─── announce with plan limit ────────────────────────────────────────────

    public function test_announce_fails_without_plan(): void
    {
        $owner     = User::factory()->create(); // free plan
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/announce", [
                'subject' => 'Test',
                'message' => 'Test message',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('plan');
    }

    public function test_non_owner_cannot_announce(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->post("/communities/{$community->slug}/announce", [
                'subject' => 'Test',
                'message' => 'Test message',
            ])
            ->assertForbidden();
    }

    // ─── updateSmsConfig ─────────────────────────────────────────────────────

    public function test_owner_can_update_sms_config(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/sms-config", [
                'sms_provider'    => 'semaphore',
                'sms_api_key'     => 'test-key-123',
                'sms_sender_name' => 'TestSender',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('communities', [
            'id'              => $community->id,
            'sms_provider'    => 'semaphore',
            'sms_api_key'     => 'test-key-123',
            'sms_sender_name' => 'TestSender',
        ]);
    }

    public function test_non_owner_cannot_update_sms_config(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->post("/communities/{$community->slug}/sms-config", [
                'sms_provider' => 'semaphore',
                'sms_api_key'  => 'hack-key',
            ])
            ->assertForbidden();
    }

    public function test_sms_config_validates_provider(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/sms-config", [
                'sms_provider' => 'invalid_provider',
            ])
            ->assertSessionHasErrors('sms_provider');
    }

    // ─── testSms ─────────────────────────────────────────────────────────────

    public function test_sms_test_requires_config_first(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'sms_provider' => null,
            'sms_api_key'  => null,
        ]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/sms-test", [
                'phone' => '+639171234567',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('sms_test');
    }

    public function test_sms_test_validates_phone_length(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'sms_provider' => 'semaphore',
            'sms_api_key'  => 'test-key',
        ]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/sms-test", [
                'phone' => '123',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('sms_test');
    }

    public function test_sms_test_sends_successfully(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'sms_provider' => 'semaphore',
            'sms_api_key'  => 'test-key',
        ]);

        $mock = $this->mock(SmsProvider::class);
        $mock->shouldReceive('blast')
            ->once()
            ->andReturn(['sent' => 1, 'failed' => 0, 'errors' => []]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/sms-test", [
                'phone' => '+639171234567',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_sms_test_returns_error_on_failure(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'sms_provider' => 'semaphore',
            'sms_api_key'  => 'test-key',
        ]);

        $mock = $this->mock(SmsProvider::class);
        $mock->shouldReceive('blast')
            ->once()
            ->andReturn(['sent' => 0, 'failed' => 1, 'errors' => ['Connection refused']]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/sms-test", [
                'phone' => '+639171234567',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('sms_test');
    }

    public function test_non_owner_cannot_test_sms(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->post("/communities/{$community->slug}/sms-test", ['phone' => '+639171234567'])
            ->assertForbidden();
    }

    // ─── sendSmsBlast ────────────────────────────────────────────────────────

    public function test_sms_blast_fails_without_sms_config(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'sms_provider' => null,
            'sms_api_key'  => null,
        ]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/sms-blast", [
                'message'     => 'Hello members!',
                'filter_type' => 'all',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('message');
    }

    public function test_sms_blast_no_recipients(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'sms_provider' => 'semaphore',
            'sms_api_key'  => 'test-key',
        ]);

        // No members with phone numbers
        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/sms-blast", [
                'message'     => 'Hello members!',
                'filter_type' => 'all',
            ])
            ->assertRedirect()
            ->assertSessionHasErrors('message');
    }

    public function test_sms_blast_success(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create(['phone' => '09171234567']);
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'sms_provider' => 'semaphore',
            'sms_api_key'  => 'test-key',
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $mock = $this->mock(SmsProvider::class);
        $mock->shouldReceive('blast')
            ->once()
            ->andReturn(['sent' => 1, 'failed' => 0, 'errors' => []]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/sms-blast", [
                'message'     => 'Hello members!',
                'filter_type' => 'all',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_sms_blast_with_failures(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create(['phone' => '09171234567']);
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'sms_provider' => 'semaphore',
            'sms_api_key'  => 'test-key',
        ]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $mock = $this->mock(SmsProvider::class);
        $mock->shouldReceive('blast')
            ->once()
            ->andReturn(['sent' => 1, 'failed' => 2, 'errors' => []]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/sms-blast", [
                'message'     => 'Hello members!',
                'filter_type' => 'all',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_non_owner_cannot_send_sms_blast(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->post("/communities/{$community->slug}/sms-blast", [
                'message'     => 'Hello',
                'filter_type' => 'all',
            ])
            ->assertForbidden();
    }

    public function test_sms_blast_validates_filter_type(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'sms_provider' => 'semaphore',
            'sms_api_key'  => 'test-key',
        ]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/sms-blast", [
                'message'     => 'Hello',
                'filter_type' => 'invalid',
            ])
            ->assertSessionHasErrors('filter_type');
    }

    // ─── landing ─────────────────────────────────────────────────────────────

    public function test_landing_page_returns_200_for_owner(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'landing_page' => ['hero' => ['headline' => 'Test']],
        ]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}/landing")
            ->assertOk();
    }

    public function test_landing_page_redirects_non_owner_when_no_landing_page(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['landing_page' => null]);

        $this->actingAs($user)
            ->get("/communities/{$community->slug}/landing")
            ->assertRedirect(route('communities.about', $community->slug));
    }

    public function test_landing_page_with_ref_sets_cookie(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'landing_page' => ['hero' => ['headline' => 'Test']],
        ]);

        $response = $this->get("/communities/{$community->slug}/landing?ref=TESTREF");

        $response->assertOk();
        $response->assertCookie('ref_code', 'TESTREF');
    }

    public function test_landing_page_non_owner_redirect_with_ref_queues_cookie(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['landing_page' => null]);

        $response = $this->actingAs($user)
            ->get("/communities/{$community->slug}/landing?ref=TESTREF");

        $response->assertRedirect();
        $this->assertStringContainsString('modal=true', $response->headers->get('Location'));
    }

    public function test_landing_page_accessible_when_landing_exists(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create([
            'landing_page' => ['hero' => ['headline' => 'Welcome']],
        ]);

        $this->actingAs($user)
            ->get("/communities/{$community->slug}/landing")
            ->assertOk();
    }

    public function test_landing_page_guest_can_access(): void
    {
        $community = Community::factory()->create([
            'landing_page' => ['hero' => ['headline' => 'Welcome']],
        ]);

        $this->get("/communities/{$community->slug}/landing")
            ->assertOk();
    }

    // ─── updateLandingPage ───────────────────────────────────────────────────

    public function test_owner_can_update_landing_page(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'landing_page' => ['hero' => ['headline' => 'Old']],
        ]);

        $response = $this->actingAs($owner)
            ->patchJson("/communities/{$community->slug}/landing-page", [
                'hero' => [
                    'headline'    => 'New Headline',
                    'subheadline' => 'New Sub',
                    'cta_label'   => 'Join Now',
                ],
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['headline' => 'New Headline']);
        $this->assertEquals('New Headline', $community->fresh()->landing_page['hero']['headline']);
    }

    public function test_non_owner_cannot_update_landing_page(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->patchJson("/communities/{$community->slug}/landing-page", [
                'hero' => [
                    'headline'    => 'Hacked',
                    'subheadline' => 'Hacked',
                    'cta_label'   => 'Hacked',
                ],
            ])
            ->assertForbidden();
    }

    public function test_super_admin_can_update_landing_page(): void
    {
        $admin     = User::factory()->create(['is_super_admin' => true]);
        $community = Community::factory()->create([
            'landing_page' => ['hero' => ['headline' => 'Old']],
        ]);

        $response = $this->actingAs($admin)
            ->patchJson("/communities/{$community->slug}/landing-page", [
                'hero' => [
                    'headline'    => 'Admin Headline',
                    'subheadline' => 'Admin Sub',
                    'cta_label'   => 'Go',
                ],
            ]);

        $response->assertOk();
    }

    public function test_update_landing_page_merges_with_existing(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'landing_page' => [
                'hero' => ['headline' => 'Original', 'subheadline' => 'Keep', 'cta_label' => 'Click'],
                'faq'  => [['question' => 'Q1', 'answer' => 'A1']],
            ],
        ]);

        $response = $this->actingAs($owner)
            ->patchJson("/communities/{$community->slug}/landing-page", [
                'hero' => [
                    'headline'    => 'Updated',
                    'subheadline' => 'Keep',
                    'cta_label'   => 'Click',
                ],
            ]);

        $response->assertOk();
        $landing = $community->fresh()->landing_page;
        $this->assertEquals('Updated', $landing['hero']['headline']);
        // FAQ should be preserved
        $this->assertArrayHasKey('faq', $landing);
    }

    public function test_update_landing_page_with_sections_metadata(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'landing_page' => ['hero' => ['headline' => 'H', 'subheadline' => 'S', 'cta_label' => 'C']],
        ]);

        $response = $this->actingAs($owner)
            ->patchJson("/communities/{$community->slug}/landing-page", [
                'hero' => ['headline' => 'H', 'subheadline' => 'S', 'cta_label' => 'C'],
                '_sections' => [
                    ['type' => 'hero', 'visible' => true],
                    ['type' => 'faq', 'visible' => false],
                ],
            ]);

        $response->assertOk();
        $landing = $community->fresh()->landing_page;
        $this->assertCount(2, $landing['_sections']);
    }

    public function test_update_landing_page_validates_hero_required(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->patchJson("/communities/{$community->slug}/landing-page", [
                'hero' => ['headline' => ''],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hero.headline');
    }

    // ─── generateLandingPage ─────────────────────────────────────────────────

    public function test_owner_can_generate_landing_page(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $mockAction = $this->mock(GenerateLandingPage::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andReturn(['hero' => ['headline' => 'Generated'], 'benefits' => [], 'faq' => []]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/ai-landing");

        $response->assertOk();
        $response->assertJsonFragment(['headline' => 'Generated']);
    }

    public function test_non_owner_cannot_generate_landing_page(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->postJson("/communities/{$community->slug}/ai-landing")
            ->assertForbidden();
    }

    public function test_generate_landing_page_handles_runtime_exception(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $mockAction = $this->mock(GenerateLandingPage::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andThrow(new \RuntimeException('AI returned an unexpected format.'));

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/ai-landing");

        $response->assertStatus(422);
        $response->assertJsonFragment(['error' => 'AI returned an unexpected format.']);
    }

    public function test_generate_landing_page_handles_generic_exception(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $mockAction = $this->mock(GenerateLandingPage::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andThrow(new \Exception('Something went wrong'));

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/ai-landing");

        $response->assertStatus(500);
        $response->assertJsonFragment(['error' => 'Something went wrong']);
    }

    // ─── regenerateSection ───────────────────────────────────────────────────

    public function test_owner_can_regenerate_section(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $mockAction = $this->mock(RegenerateLandingSection::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->withArgs(fn ($c, $s) => $c->id === $community->id && $s === 'hero')
            ->andReturn(['section' => 'hero', 'data' => ['headline' => 'Regen']]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/ai-landing/section", [
                'section' => 'hero',
            ]);

        $response->assertOk();
        $response->assertJsonFragment(['headline' => 'Regen']);
    }

    public function test_non_owner_cannot_regenerate_section(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->postJson("/communities/{$community->slug}/ai-landing/section", [
                'section' => 'hero',
            ])
            ->assertForbidden();
    }

    public function test_regenerate_section_validates_section_name(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/ai-landing/section", [
                'section' => 'invalid_section',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('section');
    }

    public function test_regenerate_section_handles_runtime_exception(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $mockAction = $this->mock(RegenerateLandingSection::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andThrow(new \RuntimeException('AI returned invalid JSON.'));

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/ai-landing/section", [
                'section' => 'hero',
            ]);

        $response->assertStatus(422);
    }

    public function test_regenerate_section_handles_generic_exception(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $mockAction = $this->mock(RegenerateLandingSection::class);
        $mockAction->shouldReceive('execute')
            ->once()
            ->andThrow(new \Exception('Unexpected error'));

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/ai-landing/section", [
                'section' => 'faq',
            ]);

        $response->assertStatus(500);
    }

    // ─── uploadSectionImage ──────────────────────────────────────────────────

    public function test_owner_can_upload_section_image(): void
    {
        Storage::fake(config('filesystems.default'));

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/landing-page/upload-image", [
                'image' => UploadedFile::fake()->image('section.jpg'),
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['url']);
    }

    public function test_non_owner_cannot_upload_section_image(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->postJson("/communities/{$community->slug}/landing-page/upload-image", [
                'image' => UploadedFile::fake()->image('section.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_upload_section_image_requires_image(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/landing-page/upload-image", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    public function test_upload_section_image_rejects_non_image(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/landing-page/upload-image", [
                'image' => UploadedFile::fake()->create('document.pdf', 100),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('image');
    }

    // ─── update with various fields ──────────────────────────────────────────

    public function test_owner_can_update_community_with_images(): void
    {
        Storage::fake(config('filesystems.default'));

        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)->patch("/communities/{$community->slug}", [
            'name'        => 'Updated Name',
            'avatar'      => UploadedFile::fake()->image('avatar.jpg'),
            'cover_image' => UploadedFile::fake()->image('cover.jpg'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('communities', ['id' => $community->id, 'name' => 'Updated Name']);
    }

    public function test_update_validates_name_required(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->patch("/communities/{$community->slug}", ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    // ─── non-owner cannot update level perks ─────────────────────────────────

    public function test_non_owner_cannot_update_level_perks(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->patch("/communities/{$community->slug}/level-perks", [
                'perks' => ['1' => 'Hacked'],
            ])
            ->assertForbidden();
    }

    // ─── non-owner cannot add gallery image ──────────────────────────────────

    public function test_non_owner_cannot_add_gallery_image(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($other)
            ->post("/communities/{$community->slug}/gallery", [
                'image' => UploadedFile::fake()->image('gallery.jpg'),
            ])
            ->assertForbidden();
    }

    public function test_non_owner_cannot_remove_gallery_image(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'       => $owner->id,
            'gallery_images' => ['/storage/img1.jpg'],
        ]);

        $this->actingAs($other)
            ->delete("/communities/{$community->slug}/gallery/0")
            ->assertForbidden();
    }

    // ─── gallery image validation ────────────────────────────────────────────

    public function test_add_gallery_image_requires_image(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/gallery", [])
            ->assertSessionHasErrors('image');
    }

    // ─── show with paid community (membership null-out for free-only members) ─

    public function test_show_nulls_out_membership_for_free_member_on_paid_community(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $member->id,
            'membership_type' => CommunityMember::MEMBERSHIP_FREE,
        ]);

        // Member exists but has no active subscription
        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}");

        $response->assertOk();
        // Membership should be null in the Inertia response since no active subscription
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Show')
            ->where('membership', null)
        );
    }

    public function test_show_keeps_membership_for_active_subscriber_on_paid_community(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        $member    = User::factory()->create();
        CommunityMember::factory()->create([
            'community_id'    => $community->id,
            'user_id'         => $member->id,
            'membership_type' => CommunityMember::MEMBERSHIP_PAID,
        ]);
        Subscription::factory()->active()->create([
            'community_id' => $community->id,
            'user_id'      => $member->id,
        ]);

        $response = $this->actingAs($member)
            ->get("/communities/{$community->slug}");

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Communities/Show')
            ->where('membership.user_id', $member->id)
        );
    }

    // ─── landing with existing ref_code cookie ──────────────────────────────

    public function test_landing_page_does_not_duplicate_cookie_when_already_set(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'landing_page' => ['hero' => ['headline' => 'Test']],
        ]);

        // When ref_code cookie already exists, no new cookie should be set
        $response = $this->withCookie('ref_code', 'EXISTING')
            ->get("/communities/{$community->slug}/landing?ref=NEWREF");

        $response->assertOk();
        // Should NOT set a new cookie since one already exists
        $response->assertCookieMissing('ref_code');
    }

    // ─── about page as owner ────────────────────────────────────────────────

    public function test_about_page_as_owner_does_not_show_invited_by(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}/about")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Communities/About')
                ->where('isOwner', true)
                ->where('invitedBy', null)
            );
    }

    // ─── update with telegram webhook ───────────────────────────────────────

    public function test_update_registers_telegram_webhook_when_token_changes(): void
    {
        // Use super_admin to bypass plan check (creatorPlan returns 'pro')
        $owner     = User::factory()->create(['is_super_admin' => true]);
        $community = Community::factory()->create([
            'owner_id'           => $owner->id,
            'telegram_bot_token' => null,
            'telegram_chat_id'   => null,
        ]);

        $telegramMock = $this->mock(\App\Services\TelegramService::class);
        $telegramMock->shouldReceive('setWebhook')->once();
        $telegramMock->shouldReceive('webhookSecret')->once()->andReturn('secret');

        $this->actingAs($owner)->patch("/communities/{$community->slug}", [
            'name'               => $community->name,
            'telegram_bot_token' => 'new-token-123',
            'telegram_chat_id'   => '-100123456',
        ]);

        $community->refresh();
        $this->assertEquals('new-token-123', $community->telegram_bot_token);
    }

    public function test_update_deletes_telegram_webhook_when_token_cleared(): void
    {
        $owner = User::factory()->create(['is_super_admin' => true]);
        $community = Community::factory()->create([
            'owner_id'           => $owner->id,
            'telegram_bot_token' => 'old-token',
            'telegram_chat_id'   => '-100123456',
        ]);

        $telegramMock = $this->mock(\App\Services\TelegramService::class);
        $telegramMock->shouldReceive('deleteWebhook')->once()->with('old-token');

        $this->actingAs($owner)->patch("/communities/{$community->slug}", [
            'name'            => $community->name,
            'telegram_clear'  => true,
        ]);

        $community->refresh();
        $this->assertNull($community->telegram_bot_token);
    }

    // ─── show with free courses ─────────────────────────────────────────────

    public function test_show_indicates_free_courses_available(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 500]);
        Course::create([
            'community_id' => $community->id,
            'title'        => 'Free Course',
            'access_type'  => 'free',
        ]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Communities/Show')
                ->where('hasFreeCourses', true)
            );
    }

    public function test_show_indicates_landing_page_exists(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'landing_page' => ['hero' => ['headline' => 'Test']],
        ]);

        $this->actingAs($owner)
            ->get("/communities/{$community->slug}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Communities/Show')
                ->where('hasLandingPage', true)
            );
    }

    // ─── update: custom domain change dispatches jobs (lines 233-238) ────────

    public function test_update_dispatches_remove_and_provision_when_custom_domain_changes(): void
    {
        Bus::fake([
            \App\Jobs\RemoveCustomDomain::class,
            \App\Jobs\ProvisionCustomDomain::class,
        ]);

        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        $community = Community::factory()->create([
            'owner_id'      => $owner->id,
            'custom_domain' => 'old.example.com',
        ]);

        $this->actingAs($owner)->patch("/communities/{$community->slug}", [
            'name'          => $community->name,
            'custom_domain' => 'new.example.com',
        ]);

        Bus::assertDispatched(\App\Jobs\RemoveCustomDomain::class, fn ($job) => true);
        Bus::assertDispatched(\App\Jobs\ProvisionCustomDomain::class, fn ($job) => true);
    }

    public function test_update_dispatches_only_remove_when_custom_domain_cleared(): void
    {
        Bus::fake([
            \App\Jobs\RemoveCustomDomain::class,
            \App\Jobs\ProvisionCustomDomain::class,
        ]);

        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        $community = Community::factory()->create([
            'owner_id'      => $owner->id,
            'custom_domain' => 'old.example.com',
        ]);

        $this->actingAs($owner)->patch("/communities/{$community->slug}", [
            'name'          => $community->name,
            'custom_domain' => null,
        ]);

        Bus::assertDispatched(\App\Jobs\RemoveCustomDomain::class);
        Bus::assertNotDispatched(\App\Jobs\ProvisionCustomDomain::class);
    }

    public function test_update_dispatches_only_provision_when_custom_domain_set_from_null(): void
    {
        Bus::fake([
            \App\Jobs\RemoveCustomDomain::class,
            \App\Jobs\ProvisionCustomDomain::class,
        ]);

        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_PRO,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        $community = Community::factory()->create([
            'owner_id'      => $owner->id,
            'custom_domain' => null,
        ]);

        $this->actingAs($owner)->patch("/communities/{$community->slug}", [
            'name'          => $community->name,
            'custom_domain' => 'new.example.com',
        ]);

        Bus::assertNotDispatched(\App\Jobs\RemoveCustomDomain::class);
        Bus::assertDispatched(\App\Jobs\ProvisionCustomDomain::class);
    }

    // ─── announce: success path with plan (lines 330-337) ────────────────────

    public function test_announce_succeeds_with_basic_plan(): void
    {
        Mail::fake();

        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_BASIC,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create([
            'community_id' => $community->id,
            'user_id'      => $owner->id,
        ]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/announce", [
                'subject' => 'Test Subject',
                'message' => 'Test body message',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');
    }

    public function test_announce_validates_subject_and_message(): void
    {
        $owner = User::factory()->create();
        CreatorSubscription::create([
            'user_id'    => $owner->id,
            'plan'       => CreatorSubscription::PLAN_BASIC,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);

        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->post("/communities/{$community->slug}/announce", [])
            ->assertSessionHasErrors(['subject', 'message']);
    }

    // ─── landing: affiliate lookup when invitedBy exists (line 455) ──────────

    public function test_landing_page_resolves_affiliate_from_ref_code(): void
    {
        $owner     = User::factory()->create();
        $referrer  = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $owner->id,
            'landing_page' => ['hero' => ['headline' => 'Welcome']],
        ]);

        $affiliate = Affiliate::create([
            'community_id' => $community->id,
            'user_id'      => $referrer->id,
            'code'         => 'TESTREF123',
            'status'       => Affiliate::STATUS_ACTIVE,
        ]);

        // Visit as unauthenticated guest with ref code
        $response = $this->get("/communities/{$community->slug}/landing?ref=TESTREF123");

        $response->assertOk();
    }
}
