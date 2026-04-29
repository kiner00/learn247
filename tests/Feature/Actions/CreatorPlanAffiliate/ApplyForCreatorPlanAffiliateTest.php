<?php

namespace Tests\Feature\Actions\CreatorPlanAffiliate;

use App\Actions\CreatorPlanAffiliate\ApplyForCreatorPlanAffiliate;
use App\Models\Affiliate;
use App\Models\CreatorPlanAffiliateApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplyForCreatorPlanAffiliateTest extends TestCase
{
    use RefreshDatabase;

    private ApplyForCreatorPlanAffiliate $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ApplyForCreatorPlanAffiliate;
    }

    public function test_creates_pending_application(): void
    {
        $user = User::factory()->create();

        $application = $this->action->execute($user, 'I have an audience of 5k creators on YouTube.');

        $this->assertInstanceOf(CreatorPlanAffiliateApplication::class, $application);
        $this->assertEquals(CreatorPlanAffiliateApplication::STATUS_PENDING, $application->status);
        $this->assertDatabaseHas('creator_plan_affiliate_applications', [
            'user_id' => $user->id,
            'status' => 'pending',
            'pitch' => 'I have an audience of 5k creators on YouTube.',
        ]);
    }

    public function test_pitch_is_optional(): void
    {
        $user = User::factory()->create();

        $application = $this->action->execute($user);

        $this->assertNull($application->pitch);
    }

    public function test_throws_if_user_already_has_pending_application(): void
    {
        $user = User::factory()->create();
        CreatorPlanAffiliateApplication::create([
            'user_id' => $user->id,
            'status' => CreatorPlanAffiliateApplication::STATUS_PENDING,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You already have a pending application under review.');
        $this->action->execute($user);
    }

    public function test_throws_if_user_is_already_creator_plan_affiliate(): void
    {
        $user = User::factory()->create();
        Affiliate::create([
            'user_id' => $user->id,
            'scope' => Affiliate::SCOPE_CREATOR_PLAN,
            'community_id' => null,
            'code' => 'EXISTINGCODE',
            'status' => Affiliate::STATUS_ACTIVE,
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('You are already a Creator Plan affiliate.');
        $this->action->execute($user);
    }

    public function test_allows_re_application_after_rejection(): void
    {
        $user = User::factory()->create();
        CreatorPlanAffiliateApplication::create([
            'user_id' => $user->id,
            'status' => CreatorPlanAffiliateApplication::STATUS_REJECTED,
            'rejection_reason' => 'Account too new',
        ]);

        $application = $this->action->execute($user, 'My account is older now.');

        $this->assertEquals(CreatorPlanAffiliateApplication::STATUS_PENDING, $application->status);
        $this->assertEquals(2, CreatorPlanAffiliateApplication::where('user_id', $user->id)->count());
    }
}
