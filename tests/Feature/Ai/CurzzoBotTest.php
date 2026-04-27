<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\CurzzoBot;
use App\Ai\Tools\GenerateImageTool;
use App\Models\Community;
use App\Models\Curzzo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurzzoBotTest extends TestCase
{
    use RefreshDatabase;

    public function test_tools_include_generate_image(): void
    {
        $community = Community::factory()->create();
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);

        $bot = new CurzzoBot($curzzo, $community);
        $tools = collect($bot->tools());

        $this->assertNotNull(
            $tools->first(fn ($t) => $t instanceof GenerateImageTool),
            'CurzzoBot must expose the GenerateImageTool so image-generator personas actually produce images.',
        );
    }

    public function test_instructions_tell_the_model_to_use_generate_image_tool(): void
    {
        $community = Community::factory()->create();
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);

        $bot = new CurzzoBot($curzzo, $community);
        $instructions = $bot->instructions();

        $this->assertStringContainsString('generate_image', $instructions);
        $this->assertStringContainsString('VERBATIM', $instructions);
    }

    public function test_instructions_teach_vertical_aspect_ratio_for_portrait_requests(): void
    {
        $community = Community::factory()->create();
        $curzzo = Curzzo::factory()->create(['community_id' => $community->id]);

        $bot = new CurzzoBot($curzzo, $community);
        $instructions = $bot->instructions();

        // Regression: without these hints the model defaults vertical/portrait
        // requests to a landscape ratio (the original 3:2 fallback).
        $this->assertStringContainsString('9:16', $instructions);
        $this->assertStringContainsString('vertical', $instructions);
        $this->assertStringContainsString('portrait', $instructions);
    }
}
