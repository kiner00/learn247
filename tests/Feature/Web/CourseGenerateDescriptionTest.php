<?php

namespace Tests\Feature\Web;

use App\Actions\Classroom\GenerateCourseDescription;
use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class CourseGenerateDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private function bindActionReturning(string $text): void
    {
        $this->app->bind(GenerateCourseDescription::class, function () use ($text) {
            return new class($text) extends GenerateCourseDescription
            {
                public function __construct(private string $stub) {}

                public function execute(Community $community, Course $course, ?int $userId): string
                {
                    return $this->stub;
                }
            };
        });
    }

    private function bindActionThrowing(\Throwable $e): void
    {
        $this->app->bind(GenerateCourseDescription::class, function () use ($e) {
            return new class($e) extends GenerateCourseDescription
            {
                public function __construct(private \Throwable $err) {}

                public function execute(Community $community, Course $course, ?int $userId): string
                {
                    throw $this->err;
                }
            };
        });
    }

    public function test_owner_can_generate_description(): void
    {
        $this->bindActionReturning('A fresh AI description.');

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/generate-description");

        $response->assertOk();
        $response->assertJson(['description' => 'A fresh AI description.']);
    }

    public function test_admin_member_can_generate_description(): void
    {
        $this->bindActionReturning('From admin.');

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);
        $admin = User::factory()->create();
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/generate-description");

        $response->assertOk();
    }

    public function test_non_manager_cannot_generate_description(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/generate-description");

        $response->assertForbidden();
    }

    public function test_returns_503_when_action_throws(): void
    {
        $this->bindActionThrowing(new RuntimeException('LLM down'));
        Log::shouldReceive('error')->once();

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/generate-description");

        $response->assertStatus(503);
        $response->assertJson(['error' => 'Description generation failed. Please try again.']);
    }

    public function test_returns_429_when_budget_cap_hit(): void
    {
        $this->bindActionThrowing(new \App\Exceptions\AiBudgetExceededException('community', 1, 5.00, 1.00, 60));

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/generate-description");

        $response->assertStatus(429);
        $response->assertJson(['error' => 'AI spending cap reached. Please try again later.']);
    }

    public function test_requires_auth(): void
    {
        $community = Community::factory()->create();
        $course = Course::factory()->create(['community_id' => $community->id]);

        $response = $this->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/generate-description");

        $response->assertStatus(401);
    }
}
