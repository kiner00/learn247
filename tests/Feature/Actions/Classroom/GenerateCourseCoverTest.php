<?php

namespace Tests\Feature\Actions\Classroom;

use App\Actions\Classroom\GenerateCourseCover;
use App\Exceptions\AiBudgetExceededException;
use App\Models\AiUsageLog;
use App\Models\Community;
use App\Models\Course;
use App\Services\StorageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use Laravel\Ai\Prompts\ImagePrompt;
use Laravel\Ai\Responses\Data\GeneratedImage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\ImageResponse;
use Tests\TestCase;

class GenerateCourseCoverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    private function fakeSuccess(?\Closure $capture = null): void
    {
        Image::fake(function (ImagePrompt $prompt) use ($capture) {
            if ($capture) {
                $capture($prompt);
            }

            return new ImageResponse(
                collect([new GeneratedImage(base64_encode('fake-png'))]),
                new Usage,
                new Meta('fake', 'fake-model'),
            );
        });
    }

    private function action(): GenerateCourseCover
    {
        return new GenerateCourseCover(app(StorageService::class));
    }

    public function test_generates_stores_and_updates_course_cover_image(): void
    {
        $this->fakeSuccess();

        $community = Community::factory()->create();
        $course = Course::factory()->create([
            'community_id' => $community->id,
            'title' => 'Productivity 101',
            'cover_image' => null,
        ]);

        $course = $this->action()->execute($community, $course, userId: 42);

        $this->assertNotNull($course->cover_image);
        $files = Storage::allFiles('course-covers/'.$community->id);
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.png', $files[0]);
        $this->assertSame('fake-png', Storage::get($files[0]));
    }

    public function test_includes_course_title_and_description_in_prompt(): void
    {
        $captured = null;
        $this->fakeSuccess(function (ImagePrompt $prompt) use (&$captured) {
            $captured = $prompt->prompt;
        });

        $community = Community::factory()->create();
        $course = Course::factory()->create([
            'community_id' => $community->id,
            'title' => 'Ship Your First SaaS',
            'description' => 'A hands-on guide to launching a production app in 30 days.',
        ]);

        $this->action()->execute($community, $course, userId: null);

        $this->assertStringContainsString('Ship Your First SaaS', $captured);
        $this->assertStringContainsString('launching a production app', $captured);
    }

    public function test_appends_extra_prompt_and_brand_context(): void
    {
        $captured = null;
        $this->fakeSuccess(function (ImagePrompt $prompt) use (&$captured) {
            $captured = $prompt->prompt;
        });

        $community = Community::factory()->create([
            'brand_context' => [
                'visual_style' => 'bold-flat',
                'color_primary' => '#FF0066',
                'target_audience' => 'indie hackers',
            ],
        ]);
        $course = Course::factory()->create([
            'community_id' => $community->id,
            'title' => 'Revenue First',
        ]);

        $this->action()->execute($community, $course, userId: null, extraPrompt: 'neon dusk, laptop on desk');

        $this->assertStringContainsString('Revenue First', $captured);
        $this->assertStringContainsString('neon dusk, laptop on desk', $captured);
        $this->assertStringContainsString('Visual style: bold-flat.', $captured);
        $this->assertStringContainsString('Primary color: #FF0066.', $captured);
        $this->assertStringContainsString('Target audience: indie hackers.', $captured);
    }

    public function test_uses_16_9_size_for_course_cover(): void
    {
        $capturedSize = null;
        Image::fake(function (ImagePrompt $prompt) use (&$capturedSize) {
            $capturedSize = $prompt->size;

            return new ImageResponse(
                collect([new GeneratedImage(base64_encode('x'))]),
                new Usage,
                new Meta('fake', 'fake-model'),
            );
        });

        $community = Community::factory()->create();
        $course = Course::factory()->create(['community_id' => $community->id]);

        $this->action()->execute($community, $course, userId: null);

        $this->assertSame('16:9', $capturedSize);
    }

    public function test_deletes_previous_cover_before_saving_new_one(): void
    {
        $this->fakeSuccess();

        $community = Community::factory()->create();

        // Seed an existing cover on disk and attach its URL to the course.
        Storage::put('course-covers/existing-old.png', 'old-bytes');
        $existingUrl = Storage::url('course-covers/existing-old.png');

        $course = Course::factory()->create([
            'community_id' => $community->id,
            'cover_image' => $existingUrl,
        ]);

        $this->action()->execute($community, $course, userId: null);

        $this->assertFalse(Storage::exists('course-covers/existing-old.png'));
        $this->assertNotSame($existingUrl, $course->fresh()->cover_image);
    }

    public function test_throws_when_community_budget_exceeded(): void
    {
        config()->set('ai_budgets.hard_caps.enabled', true);
        config()->set('ai_budgets.hard_caps.max_usd_per_community', 1.00);

        $community = Community::factory()->create();
        $course = Course::factory()->create(['community_id' => $community->id]);

        AiUsageLog::create([
            'community_id' => $community->id,
            'kind' => 'image',
            'provider' => 'fake',
            'model' => 'fake',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cost_usd' => 5.00,
            'created_at' => now(),
        ]);

        $this->expectException(AiBudgetExceededException::class);

        $this->action()->execute($community, $course, userId: null);
    }
}
