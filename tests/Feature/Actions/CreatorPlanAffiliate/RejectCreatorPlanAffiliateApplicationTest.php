<?php

namespace Tests\Feature\Actions\CreatorPlanAffiliate;

use App\Actions\CreatorPlanAffiliate\RejectCreatorPlanAffiliateApplication;
use App\Models\Affiliate;
use App\Models\CreatorPlanAffiliateApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RejectCreatorPlanAffiliateApplicationTest extends TestCase
{
    use RefreshDatabase;

    private RejectCreatorPlanAffiliateApplication $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new RejectCreatorPlanAffiliateApplication;
    }

    public function test_rejects_application_with_reason(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $application = CreatorPlanAffiliateApplication::create([
            'user_id' => $user->id,
            'status' => CreatorPlanAffiliateApplication::STATUS_PENDING,
        ]);

        $result = $this->action->execute($application, $admin, 'Account too new.');

        $this->assertEquals(CreatorPlanAffiliateApplication::STATUS_REJECTED, $result->status);
        $this->assertEquals('Account too new.', $result->rejection_reason);
        $this->assertEquals($admin->id, $result->reviewed_by);
        $this->assertNotNull($result->reviewed_at);
    }

    public function test_does_not_create_affiliate(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $application = CreatorPlanAffiliateApplication::create([
            'user_id' => $user->id,
            'status' => CreatorPlanAffiliateApplication::STATUS_PENDING,
        ]);

        $this->action->execute($application, $admin, 'no');

        $this->assertEquals(0, Affiliate::creatorPlan()->where('user_id', $user->id)->count());
    }

    public function test_throws_if_application_already_reviewed(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->create();
        $application = CreatorPlanAffiliateApplication::create([
            'user_id' => $user->id,
            'status' => CreatorPlanAffiliateApplication::STATUS_REJECTED,
            'rejection_reason' => 'old',
        ]);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('This application has already been reviewed.');
        $this->action->execute($application, $admin, 'new reason');
    }
}
