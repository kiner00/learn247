<?php

namespace Tests\Feature\Services;

use App\Models\Community;
use App\Models\Course;
use App\Models\CreatorSubscription;
use App\Models\User;
use App\Services\Community\PlanLimitService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanLimitServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlanLimitService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PlanLimitService();
    }

    // ── communityLimit ────────────────────────────────────────────────────────

    public function test_free_plan_allows_one_community(): void
    {
        $this->assertSame(1, $this->service->communityLimit('free'));
    }

    public function test_basic_plan_allows_three_communities(): void
    {
        $this->assertSame(3, $this->service->communityLimit('basic'));
    }

    public function test_pro_plan_allows_unlimited_communities(): void
    {
        $this->assertSame(PHP_INT_MAX, $this->service->communityLimit('pro'));
    }

    public function test_unknown_plan_falls_back_to_one(): void
    {
        $this->assertSame(1, $this->service->communityLimit('enterprise'));
    }

    // ── canCreateCommunity ────────────────────────────────────────────────────

    public function test_free_user_with_no_communities_can_create(): void
    {
        $user = User::factory()->create();

        $this->assertTrue($this->service->canCreateCommunity($user));
    }

    public function test_free_user_at_limit_cannot_create(): void
    {
        $user = User::factory()->create();
        Community::factory()->create(['owner_id' => $user->id]);

        $this->assertFalse($this->service->canCreateCommunity($user));
    }

    public function test_basic_user_below_limit_can_create(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'basic');

        Community::factory()->count(2)->create(['owner_id' => $user->id]);

        $this->assertTrue($this->service->canCreateCommunity($user));
    }

    public function test_basic_user_at_limit_cannot_create(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'basic');

        Community::factory()->count(3)->create(['owner_id' => $user->id]);

        $this->assertFalse($this->service->canCreateCommunity($user));
    }

    public function test_pro_user_can_always_create(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'pro');

        Community::factory()->count(10)->create(['owner_id' => $user->id]);

        $this->assertTrue($this->service->canCreateCommunity($user));
    }

    public function test_super_admin_gets_pro_plan_and_can_always_create(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        Community::factory()->count(5)->create(['owner_id' => $admin->id]);

        $this->assertTrue($this->service->canCreateCommunity($admin));
    }

    // ── communityLimitError ───────────────────────────────────────────────────

    public function test_free_limit_error_mentions_upgrade_to_basic_or_pro(): void
    {
        $user  = User::factory()->create();
        $error = $this->service->communityLimitError($user);

        $this->assertStringContainsString('1 community', $error);
        $this->assertStringContainsString('Basic', $error);
        $this->assertStringContainsString('Pro', $error);
    }

    public function test_basic_limit_error_mentions_upgrade_to_pro(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'basic');

        $error = $this->service->communityLimitError($user);

        $this->assertStringContainsString('3 communities', $error);
        $this->assertStringContainsString('Pro', $error);
        $this->assertStringNotContainsString('Basic', $error);
    }

    // ── courseLimit ───────────────────────────────────────────────────────────

    public function test_free_plan_allows_three_courses(): void
    {
        $this->assertSame(3, $this->service->courseLimit('free'));
    }

    public function test_basic_plan_allows_five_courses(): void
    {
        $this->assertSame(5, $this->service->courseLimit('basic'));
    }

    public function test_pro_plan_allows_unlimited_courses(): void
    {
        $this->assertSame(PHP_INT_MAX, $this->service->courseLimit('pro'));
    }

    // ── canCreateCourse ───────────────────────────────────────────────────────

    public function test_free_user_below_course_limit_can_create(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        Course::factory()->count(2)->create(['community_id' => $community->id]);

        $this->assertTrue($this->service->canCreateCourse($user, $community));
    }

    public function test_free_user_at_course_limit_cannot_create(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        Course::factory()->count(3)->create(['community_id' => $community->id]);

        $this->assertFalse($this->service->canCreateCourse($user, $community));
    }

    // ── canSendAnnouncement ───────────────────────────────────────────────────

    public function test_free_user_cannot_send_announcement(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->canSendAnnouncement($user));
    }

    public function test_basic_user_can_send_announcement(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'basic');

        $this->assertTrue($this->service->canSendAnnouncement($user));
    }

    public function test_pro_user_can_send_announcement(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'pro');

        $this->assertTrue($this->service->canSendAnnouncement($user));
    }

    // ── pricingGate ───────────────────────────────────────────────────────────

    public function test_pricing_gate_requires_five_paid_modules(): void
    {
        $owner     = User::factory()->create(['name' => 'John', 'bio' => 'Some bio', 'avatar' => '/img.jpg']);
        $community = Community::factory()->create([
            'owner_id'    => $owner->id,
            'cover_image' => '/cover.jpg',
            'description' => 'A community',
        ]);

        $gate = $this->service->pricingGate($community);

        $this->assertFalse($gate['can_enable_pricing']);
        $this->assertSame(0, $gate['module_count']);
    }

    public function test_pricing_gate_satisfied_when_all_requirements_met(): void
    {
        $owner     = User::factory()->create(['name' => 'Jane', 'bio' => 'My bio', 'avatar' => '/av.jpg']);
        $community = Community::factory()->create([
            'owner_id'    => $owner->id,
            'cover_image' => '/cover.jpg',
            'description' => 'A great community',
        ]);

        // Create 5 paid modules across courses
        $course = Course::factory()->create(['community_id' => $community->id]);
        for ($i = 0; $i < 5; $i++) {
            \App\Models\CourseModule::create([
                'course_id' => $course->id,
                'title'     => "Module {$i}",
                'is_free'   => false,
                'position'  => $i,
            ]);
        }

        $gate = $this->service->pricingGate($community);

        $this->assertTrue($gate['can_enable_pricing']);
        $this->assertTrue($gate['has_banner']);
        $this->assertTrue($gate['has_description']);
        $this->assertTrue($gate['profile_complete']);
        $this->assertSame(5, $gate['module_count']);
    }

    public function test_pricing_gate_fails_without_banner(): void
    {
        $owner     = User::factory()->create(['name' => 'Jane', 'bio' => 'My bio', 'avatar' => '/av.jpg']);
        $community = Community::factory()->create([
            'owner_id'    => $owner->id,
            'cover_image' => null,
            'description' => 'A community',
        ]);

        $gate = $this->service->pricingGate($community);

        $this->assertFalse($gate['has_banner']);
        $this->assertFalse($gate['can_enable_pricing']);
    }

    public function test_pricing_gate_description_whitespace_only_fails(): void
    {
        $owner     = User::factory()->create(['name' => 'Jane', 'bio' => 'My bio', 'avatar' => '/av.jpg']);
        $community = Community::factory()->create([
            'owner_id'    => $owner->id,
            'cover_image' => '/cover.jpg',
            'description' => '   ',
        ]);

        $gate = $this->service->pricingGate($community);

        $this->assertFalse($gate['has_description']);
    }

    public function test_free_modules_do_not_count_toward_pricing_gate(): void
    {
        $owner     = User::factory()->create(['name' => 'Jane', 'bio' => 'My bio', 'avatar' => '/av.jpg']);
        $community = Community::factory()->create([
            'owner_id'    => $owner->id,
            'cover_image' => '/cover.jpg',
            'description' => 'A community',
        ]);

        $course = Course::factory()->create(['community_id' => $community->id]);
        for ($i = 0; $i < 5; $i++) {
            \App\Models\CourseModule::create([
                'course_id' => $course->id,
                'title'     => "Free Module {$i}",
                'is_free'   => true,
                'position'  => $i,
            ]);
        }

        $gate = $this->service->pricingGate($community);

        $this->assertSame(0, $gate['module_count']);
        $this->assertFalse($gate['can_enable_pricing']);
    }

    // ── canUseEmail ──────────────────────────────────────────────────────────

    public function test_free_user_cannot_use_email(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->canUseEmail($user));
    }

    public function test_basic_user_can_use_email(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'basic');

        $this->assertTrue($this->service->canUseEmail($user));
    }

    public function test_pro_user_can_use_email(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'pro');

        $this->assertTrue($this->service->canUseEmail($user));
    }

    // ── canUseBYOK ───────────────────────────────────────────────────────────

    public function test_free_user_cannot_use_byok(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->canUseBYOK($user));
    }

    public function test_basic_user_cannot_use_byok(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'basic');

        $this->assertFalse($this->service->canUseBYOK($user));
    }

    public function test_pro_user_can_use_byok(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'pro');

        $this->assertTrue($this->service->canUseBYOK($user));
    }

    // ── canUploadVideo ───────────────────────────────────────────────────────

    public function test_free_user_cannot_upload_video(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->service->canUploadVideo($user));
    }

    public function test_basic_user_cannot_upload_video(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'basic');

        $this->assertFalse($this->service->canUploadVideo($user));
    }

    public function test_pro_user_can_upload_video(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'pro');

        $this->assertTrue($this->service->canUploadVideo($user));
    }

    // ── maxVideoSizeMb ───────────────────────────────────────────────────────

    public function test_pro_max_video_size_is_5120(): void
    {
        $this->assertSame(5120, $this->service->maxVideoSizeMb('pro'));
    }

    public function test_free_max_video_size_is_zero(): void
    {
        $this->assertSame(0, $this->service->maxVideoSizeMb('free'));
    }

    public function test_basic_max_video_size_is_zero(): void
    {
        $this->assertSame(0, $this->service->maxVideoSizeMb('basic'));
    }

    // ── curzzoLimit ──────────────────────────────────────────────────────────

    public function test_pro_curzzo_limit_is_five(): void
    {
        $this->assertSame(5, $this->service->curzzoLimit('pro'));
    }

    public function test_free_curzzo_limit_is_zero(): void
    {
        $this->assertSame(0, $this->service->curzzoLimit('free'));
    }

    public function test_basic_curzzo_limit_is_zero(): void
    {
        $this->assertSame(0, $this->service->curzzoLimit('basic'));
    }

    // ── canCreateCurzzo ──────────────────────────────────────────────────────

    public function test_free_user_cannot_create_curzzo(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        $this->assertFalse($this->service->canCreateCurzzo($user, $community));
    }

    public function test_basic_user_cannot_create_curzzo(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'basic');
        $community = Community::factory()->create(['owner_id' => $user->id]);

        $this->assertFalse($this->service->canCreateCurzzo($user, $community));
    }

    public function test_pro_user_can_create_curzzo_below_limit(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'pro');
        $community = Community::factory()->create(['owner_id' => $user->id]);

        $this->assertTrue($this->service->canCreateCurzzo($user, $community));
    }

    public function test_pro_user_cannot_create_curzzo_at_limit(): void
    {
        $user = User::factory()->create();
        $this->giveCreatorPlan($user, 'pro');
        $community = Community::factory()->create(['owner_id' => $user->id]);

        // Create 5 curzzos (the limit)
        for ($i = 0; $i < 5; $i++) {
            \App\Models\Curzzo::create([
                'community_id' => $community->id,
                'name'         => "Curzzo {$i}",
                'access_type'  => 'free',
                'instructions' => 'Test instructions',
                'position'     => $i,
            ]);
        }

        $this->assertFalse($this->service->canCreateCurzzo($user, $community));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function giveCreatorPlan(User $user, string $plan): void
    {
        CreatorSubscription::create([
            'user_id'    => $user->id,
            'plan'       => $plan,
            'status'     => CreatorSubscription::STATUS_ACTIVE,
            'expires_at' => now()->addYear(),
        ]);
    }
}
