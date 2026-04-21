<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountDeletionApiTest extends TestCase
{
    use RefreshDatabase;

    // ─── delete ────────────────────────────────────────────────────────────

    public function test_delete_requires_authentication(): void
    {
        $this->postJson('/api/account/delete')->assertUnauthorized();
    }

    public function test_delete_soft_deletes_user_and_revokes_tokens(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile');

        $response = $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->postJson('/api/account/delete');

        $response->assertOk()
            ->assertJsonPath('deletion.requested', true)
            ->assertJsonPath('deletion.can_cancel', true)
            ->assertJsonPath('deletion.days_remaining', User::DELETION_GRACE_DAYS);

        $this->assertSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_delete_cancels_active_recurring_subscriptions(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => User::factory()->create()->id]);

        $sub = Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'recurring_status' => 'ACTIVE',
            'xendit_plan_id' => 'plan_xyz',
        ]);

        $this->mock(\App\Services\XenditService::class, function ($m) {
            $m->shouldReceive('deactivateRecurringPlan')->with('plan_xyz')->once();
        });

        $this->actingAs($user, 'sanctum')->postJson('/api/account/delete')->assertOk();

        $this->assertEquals('INACTIVE', $sub->fresh()->recurring_status);
    }

    public function test_delete_continues_even_if_xendit_cancel_fails(): void
    {
        $user = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => User::factory()->create()->id]);

        Subscription::create([
            'community_id' => $community->id,
            'user_id' => $user->id,
            'status' => Subscription::STATUS_ACTIVE,
            'recurring_status' => 'ACTIVE',
            'xendit_plan_id' => 'plan_fail',
        ]);

        $this->mock(\App\Services\XenditService::class, function ($m) {
            $m->shouldReceive('deactivateRecurringPlan')->andThrow(new \RuntimeException('Xendit down'));
        });

        $this->actingAs($user, 'sanctum')->postJson('/api/account/delete')->assertOk();

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_soft_deleted_user_cannot_authenticate_via_sanctum(): void
    {
        $user = User::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        $user->delete();

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/auth/me')
            ->assertUnauthorized();
    }

    // ─── cancel ────────────────────────────────────────────────────────────

    public function test_cancel_restores_soft_deleted_user_within_grace(): void
    {
        $user = User::factory()->create(['password' => 'Secret1!']);
        $user->delete();

        $response = $this->postJson('/api/account/delete/cancel', [
            'email' => $user->email,
            'password' => 'Secret1!',
        ]);

        $response->assertOk()
            ->assertJsonPath('deletion.requested', false);

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_cancel_rejects_wrong_password(): void
    {
        $user = User::factory()->create(['password' => 'Secret1!']);
        $user->delete();

        $this->postJson('/api/account/delete/cancel', [
            'email' => $user->email,
            'password' => 'WrongPassword',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    public function test_cancel_rejects_user_not_pending_deletion(): void
    {
        $user = User::factory()->create(['password' => 'Secret1!']);

        $this->postJson('/api/account/delete/cancel', [
            'email' => $user->email,
            'password' => 'Secret1!',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_cancel_rejects_unknown_email(): void
    {
        $this->postJson('/api/account/delete/cancel', [
            'email' => 'ghost@example.com',
            'password' => 'anything',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_cancel_rejects_expired_grace(): void
    {
        $user = User::factory()->create(['password' => 'Secret1!']);
        $user->delete();

        $this->travel(User::DELETION_GRACE_DAYS + 1)->days();

        $this->postJson('/api/account/delete/cancel', [
            'email' => $user->email,
            'password' => 'Secret1!',
        ])->assertUnprocessable()->assertJsonValidationErrors('email');

        $this->travelBack();
    }

    public function test_cancel_validates_required_fields(): void
    {
        $this->postJson('/api/account/delete/cancel', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email', 'password']);
    }

    // ─── status ────────────────────────────────────────────────────────────

    public function test_status_requires_authentication(): void
    {
        $this->getJson('/api/account/deletion-status')->assertUnauthorized();
    }

    public function test_status_reports_not_requested_for_active_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')->getJson('/api/account/deletion-status');

        $response->assertOk()
            ->assertJsonPath('data.requested', false)
            ->assertJsonPath('data.can_cancel', false)
            ->assertJsonPath('data.days_remaining', 0);
    }

    // ─── community join guard ──────────────────────────────────────────────

    public function test_cannot_join_free_community_owned_by_pending_deletion_user(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id' => $owner->id,
            'price' => 0,
        ]);
        $owner->delete();

        $joiner = User::factory()->create();

        $response = $this->actingAs($joiner, 'sanctum')
            ->postJson("/api/communities/{$community->slug}/join");

        $response->assertUnprocessable()->assertJsonValidationErrors('community');
        $this->assertDatabaseMissing('community_members', [
            'community_id' => $community->id,
            'user_id' => $joiner->id,
        ]);
    }

    public function test_existing_members_keep_access_when_owner_requests_deletion(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        $existingMember = User::factory()->create();

        CommunityMember::create([
            'community_id' => $community->id,
            'user_id' => $existingMember->id,
            'role' => CommunityMember::ROLE_MEMBER,
            'joined_at' => now(),
        ]);

        $owner->delete();

        $this->assertDatabaseHas('community_members', [
            'community_id' => $community->id,
            'user_id' => $existingMember->id,
        ]);
    }

    public function test_community_isAcceptingNewMembers_returns_true_for_live_owner(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $this->assertTrue($community->isAcceptingNewMembers());
    }

    public function test_community_isAcceptingNewMembers_returns_false_for_trashed_owner(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $owner->delete();

        $this->assertFalse($community->fresh()->isAcceptingNewMembers());
    }
}
