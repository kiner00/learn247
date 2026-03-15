<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_get_profile_returns_own_profile_with_memberships_activity_map_badges(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create();
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile');

        $response->assertOk()
            ->assertJsonStructure([
                'user'        => ['id', 'name', 'username', 'bio', 'avatar', 'location', 'created_at'],
                'memberships',
                'activity_map',
                'badges',
            ])
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonPath('is_own', true);
    }

    public function test_get_users_username_returns_other_user_profile(): void
    {
        $viewer  = User::factory()->create();
        $profile = User::factory()->create(['username' => 'johndoe']);

        $response = $this->actingAs($viewer, 'sanctum')
            ->getJson('/api/users/johndoe');

        $response->assertOk()
            ->assertJsonPath('user.id', $profile->id)
            ->assertJsonPath('user.username', 'johndoe')
            ->assertJsonPath('is_own', false);
    }

    public function test_patch_profile_updates_name_bio_location(): void
    {
        $user = User::factory()->create([
            'name'     => 'Old Name',
            'bio'      => 'Old bio',
            'location' => 'Old location',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'name'     => 'New Name',
                'bio'      => 'New bio',
                'location' => 'Manila',
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Profile updated.')
            ->assertJsonPath('user.name', 'New Name')
            ->assertJsonPath('user.bio', 'New bio')
            ->assertJsonPath('user.location', 'Manila');

        $user->refresh();
        $this->assertSame('New Name', $user->name);
        $this->assertSame('New bio', $user->bio);
        $this->assertSame('Manila', $user->location);
    }

    public function test_patch_profile_validation_name_max_255_bio_max_500(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->patchJson('/api/profile', [
                'name' => str_repeat('a', 256),
                'bio'  => str_repeat('b', 501),
            ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'bio']);
    }

    public function test_get_profile_unauthenticated_returns_401(): void
    {
        $this->getJson('/api/profile')
            ->assertUnauthorized();
    }
}
