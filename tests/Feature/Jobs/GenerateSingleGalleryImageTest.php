<?php

namespace Tests\Feature\Jobs;

use App\Jobs\GenerateSingleGalleryImage;
use App\Models\Community;
use App\Models\CommunityGalleryItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use Laravel\Ai\Prompts\ImagePrompt;
use Laravel\Ai\Responses\Data\GeneratedImage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\ImageResponse;
use Tests\TestCase;

class GenerateSingleGalleryImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        Cache::flush();
    }

    private function fakeImageWith(string $base64): void
    {
        Image::fake(function (ImagePrompt $prompt) use ($base64) {
            return new ImageResponse(
                collect([new GeneratedImage($base64)]),
                new Usage,
                new Meta('fake', 'fake-model'),
            );
        });
    }

    public function test_generates_and_stores_image_and_appends_to_gallery(): void
    {
        Bus::fake();
        $this->fakeImageWith(base64_encode('hello-image-bytes'));

        $community = Community::factory()->create();

        // Only generate 1 total image, so no next dispatch.
        $job = new GenerateSingleGalleryImage($community, promptIndex: 7, total: 1);
        $job->handle();

        $items = $community->galleryItems()->get();
        $this->assertCount(1, $items);
        $this->assertEquals('image', $items[0]->type);
        $this->assertStringContainsString('community-gallery/'.$community->id.'_8_', $items[0]->image_path);

        // Cache should show completed
        $cache = Cache::get("gallery-generating:{$community->id}");
        $this->assertEquals('completed', $cache['status']);
        $this->assertEquals(1, $cache['progress']);
        $this->assertEquals(1, $cache['total']);

        // No further dispatch
        Bus::assertNotDispatched(GenerateSingleGalleryImage::class);
    }

    public function test_dispatches_next_job_when_more_images_remain(): void
    {
        Bus::fake();
        $this->fakeImageWith(base64_encode('bytes'));

        $community = Community::factory()->create();

        // total=8, index=0 — should dispatch index=1 next.
        $job = new GenerateSingleGalleryImage($community, promptIndex: 0, total: 8);
        $job->handle();

        Bus::assertDispatched(GenerateSingleGalleryImage::class, function ($dispatched) use ($community) {
            return $dispatched->community->id === $community->id
                && $dispatched->promptIndex === 1
                && $dispatched->total === 8;
        });

        $cache = Cache::get("gallery-generating:{$community->id}");
        $this->assertEquals('generating', $cache['status']);
        $this->assertEquals(1, $cache['progress']);
    }

    public function test_incorporates_brand_context_into_prompt(): void
    {
        Bus::fake();

        $capturedPrompt = null;
        Image::fake(function (ImagePrompt $prompt) use (&$capturedPrompt) {
            $capturedPrompt = $prompt->prompt;

            return new ImageResponse(
                collect([new GeneratedImage(base64_encode('x'))]),
                new Usage,
                new Meta('fake', 'fake-model'),
            );
        });

        $community = Community::factory()->create([
            'brand_context' => [
                'visual_style' => 'flat-illustration',
                'brand_personality' => 'Professional yet gritty',
                'color_primary' => '#FF0000',
                'color_secondary' => '#00FF00',
                'color_accent' => '#0000FF',
                'target_audience' => 'aspiring filmmakers',
                'value_proposition' => 'Ship your first film',
                'cta_goal' => 'Start Filming',
                'big_problem' => 'procrastination',
                'offer_details' => '30% off for 24 hours',
            ],
        ]);

        // Prompt index 0 = welcome banner (has brand suffix)
        $job = new GenerateSingleGalleryImage($community, promptIndex: 0, total: 1);
        $job->handle();

        $this->assertNotNull($capturedPrompt);
        $this->assertStringContainsString('Visual style: flat-illustration.', $capturedPrompt);
        $this->assertStringContainsString('Brand personality: Professional yet gritty.', $capturedPrompt);
        $this->assertStringContainsString('Primary color: #FF0000.', $capturedPrompt);
        $this->assertStringContainsString('Secondary color: #00FF00.', $capturedPrompt);
        $this->assertStringContainsString('Accent color: #0000FF.', $capturedPrompt);
        $this->assertStringContainsString('Target audience: aspiring filmmakers.', $capturedPrompt);
        $this->assertStringContainsString('Value proposition: Ship your first film.', $capturedPrompt);
    }

    public function test_cta_prompt_uses_custom_cta_and_offer(): void
    {
        Bus::fake();

        $capturedPrompt = null;
        Image::fake(function (ImagePrompt $prompt) use (&$capturedPrompt) {
            $capturedPrompt = $prompt->prompt;

            return new ImageResponse(
                collect([new GeneratedImage(base64_encode('x'))]),
                new Usage,
                new Meta('fake', 'fake-model'),
            );
        });

        $community = Community::factory()->create([
            'brand_context' => [
                'cta_goal' => 'Enroll Today',
                'offer_details' => 'Limited 50% discount',
                'big_problem' => 'stuck at day job',
            ],
        ]);

        // promptIndex 7 = CTA
        $job = new GenerateSingleGalleryImage($community, promptIndex: 7, total: 1);
        $job->handle();

        $this->assertStringContainsString("'Enroll Today'", $capturedPrompt);
        $this->assertStringContainsString('Featuring: Limited 50% discount.', $capturedPrompt);
    }

    public function test_uses_sensible_defaults_when_brand_context_empty(): void
    {
        Bus::fake();

        $capturedPrompt = null;
        Image::fake(function (ImagePrompt $prompt) use (&$capturedPrompt) {
            $capturedPrompt = $prompt->prompt;

            return new ImageResponse(
                collect([new GeneratedImage(base64_encode('x'))]),
                new Usage,
                new Meta('fake', 'fake-model'),
            );
        });

        $community = Community::factory()->create([
            'brand_context' => null,
            'category' => null,
        ]);

        // index 7 = CTA, should use 'Join Now' default
        $job = new GenerateSingleGalleryImage($community, promptIndex: 7, total: 1);
        $job->handle();

        $this->assertStringContainsString("'Join Now'", $capturedPrompt);
        // No brand suffix
        $this->assertStringNotContainsString('Visual style:', $capturedPrompt);
    }

    public function test_handles_image_generation_failure_gracefully(): void
    {
        Bus::fake();

        Image::fake(function () {
            throw new \RuntimeException('AI provider blew up');
        });

        Log::shouldReceive('error')->once();

        $community = Community::factory()->create();

        $job = new GenerateSingleGalleryImage($community, promptIndex: 0, total: 3);
        $job->handle();

        $cache = Cache::get("gallery-generating:{$community->id}");
        $this->assertEquals('failed', $cache['status']);
        $this->assertEquals('Image generation failed. Please try again.', $cache['error']);
        $this->assertEquals(3, $cache['total']);

        // Gallery untouched
        $this->assertCount(0, $community->galleryItems()->get());

        // Should NOT dispatch next
        Bus::assertNotDispatched(GenerateSingleGalleryImage::class);
    }

    public function test_failed_method_writes_failed_cache_entry(): void
    {
        Log::shouldReceive('error')->once();

        $community = Community::factory()->create();

        $job = new GenerateSingleGalleryImage($community, promptIndex: 5, total: 3);
        $job->failed(new \RuntimeException('queue killed'));

        $cache = Cache::get("gallery-generating:{$community->id}");
        $this->assertEquals('failed', $cache['status']);
        $this->assertEquals(3, $cache['total']);
    }

    public function test_job_has_correct_retry_and_timeout_configuration(): void
    {
        $community = Community::factory()->create();

        $job = new GenerateSingleGalleryImage($community, 0, 8);

        $this->assertEquals(1, $job->tries);
        $this->assertEquals(120, $job->timeout);
    }

    public function test_falls_back_to_first_prompt_when_index_out_of_range(): void
    {
        Bus::fake();

        $capturedPrompt = null;
        Image::fake(function (ImagePrompt $prompt) use (&$capturedPrompt) {
            $capturedPrompt = $prompt->prompt;

            return new ImageResponse(
                collect([new GeneratedImage(base64_encode('x'))]),
                new Usage,
                new Meta('fake', 'fake-model'),
            );
        });

        $community = Community::factory()->create([
            'name' => 'Test Community',
        ]);

        // index 99 is out of range → falls back to $prompts[0] (welcome banner)
        $job = new GenerateSingleGalleryImage($community, promptIndex: 99, total: 1);
        $job->handle();

        $this->assertStringContainsString('Welcome to Test Community', $capturedPrompt);
    }

    public function test_appends_to_existing_gallery_instead_of_replacing(): void
    {
        Bus::fake();
        $this->fakeImageWith(base64_encode('x'));

        $community = Community::factory()->create();
        CommunityGalleryItem::create([
            'community_id' => $community->id,
            'type' => 'image',
            'image_path' => 'community-gallery/existing-1.png',
            'position' => 0,
        ]);
        CommunityGalleryItem::create([
            'community_id' => $community->id,
            'type' => 'image',
            'image_path' => 'community-gallery/existing-2.png',
            'position' => 1,
        ]);

        $job = new GenerateSingleGalleryImage($community, promptIndex: 7, total: 1);
        $job->handle();

        $items = $community->galleryItems()->get();
        $this->assertCount(3, $items);
        $this->assertEquals('community-gallery/existing-1.png', $items[0]->image_path);
        $this->assertEquals('community-gallery/existing-2.png', $items[1]->image_path);
        $this->assertEquals('image', $items[2]->type);
        $this->assertEquals(2, $items[2]->position);
    }
}
