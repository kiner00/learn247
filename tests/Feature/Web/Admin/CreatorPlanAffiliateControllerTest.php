<?php

namespace Tests\Feature\Web\Admin;

use App\Models\Affiliate;
use App\Models\CreatorPlanAffiliateApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreatorPlanAffiliateControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_requires_super_admin(): void
    {
        $user = User::factory()->create(['is_super_admin' => false]);

        $this->actingAs($user)->get('/admin/creator-plan-affiliates')->assertForbidden();
    }

    public function test_index_renders_for_super_admin(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);

        $this->actingAs($admin)->get('/admin/creator-plan-affiliates')->assertOk();
    }

    public function test_approve_creates_affiliate_and_marks_application(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $user = User::factory()->create();
        $application = CreatorPlanAffiliateApplication::create([
            'user_id' => $user->id,
            'status' => CreatorPlanAffiliateApplication::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch("/admin/creator-plan-affiliates/{$application->id}/approve")
            ->assertRedirect();

        $this->assertEquals(CreatorPlanAffiliateApplication::STATUS_APPROVED, $application->fresh()->status);
        $this->assertTrue(
            Affiliate::creatorPlan()->where('user_id', $user->id)->exists()
        );
    }

    public function test_reject_requires_reason(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $user = User::factory()->create();
        $application = CreatorPlanAffiliateApplication::create([
            'user_id' => $user->id,
            'status' => CreatorPlanAffiliateApplication::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch("/admin/creator-plan-affiliates/{$application->id}/reject", [])
            ->assertSessionHasErrors('reason');
    }

    public function test_reject_marks_rejected_with_reason(): void
    {
        $admin = User::factory()->create(['is_super_admin' => true]);
        $user = User::factory()->create();
        $application = CreatorPlanAffiliateApplication::create([
            'user_id' => $user->id,
            'status' => CreatorPlanAffiliateApplication::STATUS_PENDING,
        ]);

        $this->actingAs($admin)
            ->patch("/admin/creator-plan-affiliates/{$application->id}/reject", [
                'reason' => 'Not aligned with the program.',
            ])
            ->assertRedirect();

        $application->refresh();
        $this->assertEquals(CreatorPlanAffiliateApplication::STATUS_REJECTED, $application->status);
        $this->assertEquals('Not aligned with the program.', $application->rejection_reason);
    }

    public function test_user_apply_endpoint_creates_pending_application(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/my-affiliates/creator-plan/apply', ['pitch' => 'Big audience.'])
            ->assertRedirect();

        $this->assertDatabaseHas('creator_plan_affiliate_applications', [
            'user_id' => $user->id,
            'status' => 'pending',
            'pitch' => 'Big audience.',
        ]);
    }
}
