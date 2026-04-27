<?php

namespace Tests\Feature\Ai\Tools;

use App\Ai\Tools\GenerateImageTool;
use App\Models\AiUsageLog;
use App\Models\Community;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Image;
use Laravel\Ai\Prompts\ImagePrompt;
use Laravel\Ai\Responses\Data\GeneratedImage;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\Usage;
use Laravel\Ai\Responses\ImageResponse;
use Laravel\Ai\Tools\Request;
use Tests\TestCase;

class GenerateImageToolTest extends TestCase
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
                collect([new GeneratedImage(base64_encode('fake-png-bytes'))]),
                new Usage,
                new Meta('fake', 'fake-model'),
            );
        });
    }

    public function test_returns_markdown_image_link_and_stores_png(): void
    {
        $capturedSize = null;
        Image::fake(function (ImagePrompt $prompt) use (&$capturedSize) {
            $capturedSize = $prompt->size;

            return new ImageResponse(
                collect([new GeneratedImage(base64_encode('fake-png-bytes'))]),
                new Usage,
                new Meta('fake', 'fake-model'),
            );
        });
        $community = Community::factory()->create();

        $tool = new GenerateImageTool($community, userId: 42);
        $result = $tool->handle(new Request([
            'prompt' => 'A vibrant course banner for Productivity 101',
            'aspect_ratio' => '16:9',
        ]));

        $this->assertStringContainsString('![generated image](', $result);
        $this->assertSame('16:9', $capturedSize);

        // The stored file lives under curzzo-chat-images/{community_id}/*.png
        $files = Storage::allFiles('curzzo-chat-images/'.$community->id);
        $this->assertCount(1, $files);
        $this->assertStringEndsWith('.png', $files[0]);
        $this->assertSame('fake-png-bytes', Storage::get($files[0]));
    }

    public function test_injects_brand_context_into_prompt(): void
    {
        $captured = null;
        $this->fakeSuccess(function (ImagePrompt $prompt) use (&$captured) {
            $captured = $prompt->prompt;
        });

        $community = Community::factory()->create([
            'brand_context' => [
                'visual_style' => 'bold-flat',
                'brand_personality' => 'Professional yet gritty',
                'color_primary' => '#111827',
                'color_secondary' => '#6366F1',
                'color_accent' => '#F59E0B',
                'target_audience' => 'aspiring founders',
            ],
        ]);

        $tool = new GenerateImageTool($community);
        $tool->handle(new Request(['prompt' => 'A hero banner showing a rocket launch']));

        $this->assertStringContainsString('A hero banner showing a rocket launch', $captured);
        $this->assertStringContainsString('Visual style: bold-flat.', $captured);
        $this->assertStringContainsString('Brand personality: Professional yet gritty.', $captured);
        $this->assertStringContainsString('Primary color: #111827.', $captured);
        $this->assertStringContainsString('Secondary color: #6366F1.', $captured);
        $this->assertStringContainsString('Accent color: #F59E0B.', $captured);
        $this->assertStringContainsString('Target audience: aspiring founders.', $captured);
    }

    public function test_passes_raw_prompt_when_brand_context_missing(): void
    {
        $captured = null;
        $this->fakeSuccess(function (ImagePrompt $prompt) use (&$captured) {
            $captured = $prompt->prompt;
        });

        $community = Community::factory()->create(['brand_context' => null]);

        $tool = new GenerateImageTool($community);
        $tool->handle(new Request(['prompt' => 'A clean flat illustration']));

        $this->assertSame('A clean flat illustration', $captured);
    }

    public function test_forwards_vertical_aspect_ratio_to_image_provider(): void
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

        $tool = new GenerateImageTool($community);
        $tool->handle(new Request([
            'prompt' => 'a portrait of man standing with ford mustang',
            'aspect_ratio' => '9:16',
        ]));

        // Regression: a vertical aspect must reach the provider untouched. Earlier
        // tool-description guidance silently fell back to 3:2 (landscape) for 9:16 prompts.
        $this->assertSame('9:16', $capturedSize);
    }

    public function test_falls_back_to_default_aspect_when_invalid(): void
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

        $tool = new GenerateImageTool($community);
        $tool->handle(new Request(['prompt' => 'p', 'aspect_ratio' => 'nonsense-ratio']));

        $this->assertSame('3:2', $capturedSize);
    }

    public function test_returns_error_when_prompt_is_empty(): void
    {
        $community = Community::factory()->create();

        $tool = new GenerateImageTool($community);
        $result = $tool->handle(new Request(['prompt' => '   ']));

        $this->assertStringStartsWith('Error:', $result);
        $this->assertSame([], Storage::allFiles('curzzo-chat-images/'.$community->id));
    }

    public function test_returns_error_when_image_provider_throws(): void
    {
        Log::shouldReceive('error')->once();

        Image::fake(function () {
            throw new \RuntimeException('provider exploded');
        });

        $community = Community::factory()->create();

        $tool = new GenerateImageTool($community);
        $result = $tool->handle(new Request(['prompt' => 'anything']));

        $this->assertStringStartsWith('Error:', $result);
        // The real provider error must surface so the agent can tell the member what went wrong.
        $this->assertStringContainsString('provider exploded', $result);
        $this->assertSame([], Storage::allFiles('curzzo-chat-images/'.$community->id));
    }

    public function test_returns_error_when_prompt_exceeds_max_length(): void
    {
        // Provider must never be called — asserted by omitting Image::fake. Any call would error.
        $community = Community::factory()->create();

        $tool = new GenerateImageTool($community);
        $result = $tool->handle(new Request([
            'prompt' => str_repeat('a', GenerateImageTool::MAX_PROMPT_LENGTH + 1),
        ]));

        $this->assertStringStartsWith('Error:', $result);
        $this->assertStringContainsString('too long', $result);
        $this->assertStringContainsString((string) GenerateImageTool::MAX_PROMPT_LENGTH, $result);
        $this->assertSame([], Storage::allFiles('curzzo-chat-images/'.$community->id));
    }

    public function test_accepts_prompt_exactly_at_max_length(): void
    {
        $this->fakeSuccess();
        $community = Community::factory()->create();

        $tool = new GenerateImageTool($community);
        $result = $tool->handle(new Request([
            'prompt' => str_repeat('a', GenerateImageTool::MAX_PROMPT_LENGTH),
        ]));

        $this->assertStringContainsString('![generated image](', $result);
    }

    public function test_blocks_generation_when_community_budget_exceeded(): void
    {
        config()->set('ai_budgets.hard_caps.enabled', true);
        config()->set('ai_budgets.hard_caps.max_usd_per_community', 1.00);
        config()->set('ai_budgets.hard_caps.window_minutes', 60);

        $community = Community::factory()->create();

        AiUsageLog::create([
            'community_id' => $community->id,
            'user_id' => null,
            'kind' => 'image',
            'provider' => 'fake',
            'model' => 'fake',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cost_usd' => 5.00,
            'created_at' => now(),
        ]);

        Log::shouldReceive('warning')->once();

        $tool = new GenerateImageTool($community);
        $result = $tool->handle(new Request(['prompt' => 'anything']));

        $this->assertStringContainsString('AI spending cap', $result);
        $this->assertSame([], Storage::allFiles('curzzo-chat-images/'.$community->id));
    }

    public function test_tool_is_named_generate_image(): void
    {
        $community = Community::factory()->create();
        $tool = new GenerateImageTool($community);

        $this->assertSame('generate_image', $tool->name());
    }
}
