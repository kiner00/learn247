<?php

namespace Tests\Feature\Api;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassroomControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_list_courses(): void
    {
        $owner     = User::factory()->create();
        $member    = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id, 'price' => 0]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $member->id]);

        Course::create([
            'community_id' => $community->id,
            'title'       => 'Test Course',
            'description' => 'Desc',
        ]);

        $this->actingAs($member)
            ->getJson("/api/communities/{$community->slug}/courses")
            ->assertOk()
            ->assertJsonStructure(['courses']);
    }

    public function test_non_member_gets_403_when_listing_courses(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['price' => 0]);

        $this->actingAs($user)
            ->getJson("/api/communities/{$community->slug}/courses")
            ->assertForbidden();
    }

    public function test_owner_can_create_course_returns_201(): void
    {
        $owner     = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);

        $response = $this->actingAs($owner)
            ->postJson("/api/communities/{$community->slug}/courses", [
                'title'       => 'New Course',
                'description' => 'Course description',
            ])
            ->assertStatus(201)
            ->assertJsonPath('message', 'Course created.')
            ->assertJsonStructure(['course_id']);

        $this->assertDatabaseHas('courses', [
            'community_id' => $community->id,
            'title'        => 'New Course',
            'description'  => 'Course description',
        ]);
    }

    public function test_non_owner_gets_403_when_creating_course(): void
    {
        $owner     = User::factory()->create();
        $otherUser = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        CommunityMember::factory()->create(['community_id' => $community->id, 'user_id' => $otherUser->id]);

        $this->actingAs($otherUser)
            ->postJson("/api/communities/{$community->slug}/courses", [
                'title'       => 'New Course',
                'description' => 'Course description',
            ])
            ->assertForbidden();
    }

    public function test_unauthenticated_returns_401_when_listing_courses(): void
    {
        $community = Community::factory()->create();

        $this->getJson("/api/communities/{$community->slug}/courses")
            ->assertUnauthorized();
    }

    public function test_unauthenticated_returns_401_when_creating_course(): void
    {
        $community = Community::factory()->create();

        $this->postJson("/api/communities/{$community->slug}/courses", [
            'title' => 'New Course',
        ])
            ->assertUnauthorized();
    }
}
