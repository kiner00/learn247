<?php

namespace Tests\Feature\Actions\CreatorPlanAffiliate;

use App\Actions\CreatorPlanAffiliate\ApproveCreatorPlanAffiliateApplication;
use App\Models\Affiliate;
use App\Models\CreatorPlanAffiliateApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApproveCreatorPlanAffiliateApplicationTest extends TestCase
{
    use RefreshDatabase;

    private ApproveCreatorPlanAffiliateApplication $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ApproveCreatorPlanAffiliateApplication;
    }

    public function test_approves_application_and_creates_affiliate(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $application = CreatorPlanAffiliateApplication::create([
            'user_id' => $user->id,
            'status' => CreatorPlanAffiliateApplication::STATUS_PENDING,
            'pitch' => 'My pitch',
        ]);

        $affiliate = $this->action->execute($application, $admin);

        $this->assertInstanceOf(Affiliate::class, $affiliate);
        $this->assertEquals(Affiliate::SCOPE_CREATOR_PLAN, $affiliate->scope);
        $this->assertNull($affiliate->community_id);
        $this->assertEquals($user->id, $affiliate->user_id);
        $this->assertEquals(Affiliate::STATUS_ACTIVE, $affiliate->status);
        $this->assertNotEmpty($affiliate->code);

        $application->refresh();
        $this->assertEquals(CreatorPlanAffiliateApplication::STATUS_APPROVED, $application->status);
        $this->assertEquals($admin->id, $application->reviewed_by);
        $this->assertNotNull($application->reviewed_at);
    }

    public function test_is_idempotent_if_affiliate_already_exists(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $existing = Affiliate::create([
            'user_id' => $user->id,
            'scope' => Affiliate::SCOPE_CREATOR_PLAN,
            'community_id' => null,
            'code' => 'PRESEEDED',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);
        $application = CreatorPlanAffiliateApplication::create([
            'user_id' => $user->id,
            'status' => CreatorPlanAffiliateApplication::STATUS_PENDING,
        ]);

        $affiliate = $this->action->execute($application, $admin);

        $this->assertEquals($existing->id, $affiliate->id);
        $this->assertEquals(1, Affiliate::creatorPlan()->where('user_id', $user->id)->count());
    }

    public function test_throws_if_application_already_reviewed(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $application = CreatorPlanAffiliateApplication::create([
            'user_id' => $user->id,
            'status' => CreatorPlanAffiliateApplication::STATUS_APPROVED,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This application has already been reviewed.');
        $this->action->execute($application, $admin);
    }
}
