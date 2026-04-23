<?php

namespace Tests\Feature\Web;

use App\Models\Community;
use App\Models\CommunityMember;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use Laravel\Ai\Prompts\ImagePrompt;
use Laravel\Ai\Responses\Data\GeneratedImage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\ImageResponse;
use Tests\TestCase;

class CourseGenerateCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    private function fakeSuccess(): void
    {
        Image::fake(fn (ImagePrompt $p) => new ImageResponse(
            collect([new GeneratedImage(base64_encode('png'))]),
            new Usage,
            new Meta('fake', 'fake-model'),
        ));
    }

    public function test_owner_can_generate_cover_and_receives_url(): void
    {
        $this->fakeSuccess();

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/generate-cover", [
                'prompt' => 'cinematic lighting',
            ]);

        $response->assertOk();
        $response->assertJsonStructure(['cover_image']);

        $this->assertNotNull($course->fresh()->cover_image);
    }

    public function test_non_manager_cannot_generate_cover(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);
        $stranger = User::factory()->create();

        $response = $this->actingAs($stranger)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/generate-cover");

        $response->assertForbidden();
    }

    public function test_admin_member_can_generate_cover(): void
    {
        $this->fakeSuccess();

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);
        $admin = User::factory()->create();
        CommunityMember::factory()->admin()->create([
            'community_id' => $community->id,
            'user_id' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/generate-cover");

        $response->assertOk();
    }

    public function test_returns_503_when_image_provider_throws(): void
    {
        Image::fake(fn () => throw new \RuntimeException('gemini down'));
        Log::shouldReceive('error')->once();

        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/generate-cover");

        $response->assertStatus(503);
        $response->assertJson(['error' => 'Image generation failed. Please try again.']);
    }

    public function test_validates_prompt_length(): void
    {
        $owner = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $owner->id]);
        $course = Course::factory()->create(['community_id' => $community->id]);

        $response = $this->actingAs($owner)
            ->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/generate-cover", [
                'prompt' => str_repeat('x', 501),
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['prompt']);
    }

    public function test_requires_auth(): void
    {
        $community = Community::factory()->create();
        $course = Course::factory()->create(['community_id' => $community->id]);

        $response = $this->postJson("/communities/{$community->slug}/classroom/courses/{$course->id}/generate-cover");

        $response->assertStatus(401);
    }
}
