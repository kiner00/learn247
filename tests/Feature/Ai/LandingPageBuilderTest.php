<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\LandingPageBuilder;
use Tests\TestCase;

class LandingPageBuilderTest extends TestCase
{
    // ── instructions() content ────────────────────────────────────────────────

    public function test_instructions_include_community_name(): void
    {
        $agent = new LandingPageBuilder(['name' => 'Laravel Mastery']);

        $this->assertStringContainsString('Laravel Mastery', $agent->instructions());
    }

    public function test_instructions_include_category(): void
    {
        $agent = new LandingPageBuilder(['name' => 'My Community', 'category' => 'Education']);

        $this->assertStringContainsString('Education', $agent->instructions());
    }

    public function test_instructions_default_category_to_general(): void
    {
        $agent = new LandingPageBuilder(['name' => 'My Community']);

        $this->assertStringContainsString('General', $agent->instructions());
    }

    public function test_instructions_include_existing_description(): void
    {
        $agent = new LandingPageBuilder([
            'name'        => 'My Community',
            'description' => 'We help developers grow.',
        ]);

        $this->assertStringContainsString('We help developers grow.', $agent->instructions());
    }

    public function test_instructions_handle_missing_description_gracefully(): void
    {
        $agent = new LandingPageBuilder(['name' => 'My Community']);

        // Should not throw — description defaults to empty string
        $instructions = $agent->instructions();
        $this->assertNotEmpty($instructions);
    }

    // ── JSON output format ────────────────────────────────────────────────────

    public function test_instructions_specify_json_output_with_required_keys(): void
    {
        $agent        = new LandingPageBuilder(['name' => 'Test']);
        $instructions = $agent->instructions();

        $this->assertStringContainsString('"headline"', $instructions);
        $this->assertStringContainsString('"benefits"', $instructions);
        $this->assertStringContainsString('"cta_label"', $instructions);
    }

    public function test_instructions_mention_max_character_limits(): void
    {
        $agent        = new LandingPageBuilder(['name' => 'Test']);
        $instructions = $agent->instructions();

        $this->assertStringContainsString('80', $instructions);  // tagline limit
        $this->assertStringContainsString('300', $instructions); // description limit
        $this->assertStringContainsString('30', $instructions);  // cta limit
    }

    public function test_instructions_require_no_markdown_in_output(): void
    {
        $agent        = new LandingPageBuilder(['name' => 'Test']);
        $instructions = $agent->instructions();

        $this->assertStringContainsString('no markdown', $instructions);
        $this->assertStringContainsString('valid JSON', $instructions);
    }

    // ── Strict rules ──────────────────────────────────────────────────────────

    public function test_instructions_prohibit_inventing_facts(): void
    {
        $agent        = new LandingPageBuilder(['name' => 'Test']);
        $instructions = $agent->instructions();

        $this->assertStringContainsString('specific', $instructions);
    }

    public function test_instructions_require_active_voice(): void
    {
        $agent        = new LandingPageBuilder(['name' => 'Test']);
        $instructions = $agent->instructions();

        $this->assertStringContainsString('active voice', $instructions);
    }

    // ── Edge cases ────────────────────────────────────────────────────────────

    public function test_instructions_with_special_characters_in_name(): void
    {
        $agent = new LandingPageBuilder(['name' => "Chef's Kitchen & Co."]);

        $instructions = $agent->instructions();

        $this->assertStringContainsString("Chef's Kitchen & Co.", $instructions);
    }

    public function test_instructions_with_all_fields_populated(): void
    {
        $agent = new LandingPageBuilder([
            'name'        => 'Full Stack Ninjas',
            'category'    => 'Tech',
            'description' => 'Master full-stack development.',
        ]);

        $instructions = $agent->instructions();

        $this->assertStringContainsString('Full Stack Ninjas', $instructions);
        $this->assertStringContainsString('Tech', $instructions);
        $this->assertStringContainsString('Master full-stack development.', $instructions);
    }
}
