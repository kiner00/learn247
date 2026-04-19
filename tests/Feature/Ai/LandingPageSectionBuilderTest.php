<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\LandingPageSectionBuilder;
use Tests\TestCase;

class LandingPageSectionBuilderTest extends TestCase
{
    private function makeAgent(array $overrides = []): LandingPageSectionBuilder
    {
        return new LandingPageSectionBuilder(array_merge([
            'name' => 'Test Community',
            'category' => 'Education',
            'description' => 'A great community',
            'price' => 499,
            'currency' => 'PHP',
            'creator_name' => 'John Doe',
            'member_count' => 100,
            'section' => 'hero',
        ], $overrides));
    }

    // ── instructions() basics ───────────────────────────────────────────────

    public function test_instructions_include_community_details(): void
    {
        $instructions = $this->makeAgent()->instructions();

        $this->assertStringContainsString('Test Community', $instructions);
        $this->assertStringContainsString('Education', $instructions);
        $this->assertStringContainsString('A great community', $instructions);
        $this->assertStringContainsString('John Doe', $instructions);
        $this->assertStringContainsString('PHP 499', $instructions);
        $this->assertStringContainsString('100', $instructions);
    }

    public function test_instructions_show_free_when_price_is_zero(): void
    {
        $instructions = $this->makeAgent(['price' => 0])->instructions();

        $this->assertStringContainsString('Free', $instructions);
    }

    public function test_instructions_defaults_when_optional_fields_missing(): void
    {
        $agent = new LandingPageSectionBuilder([
            'name' => 'Minimal',
            'section' => 'hero',
        ]);

        $instructions = $agent->instructions();

        $this->assertStringContainsString('General', $instructions);       // default category
        $this->assertStringContainsString('the creator', $instructions);   // default creator_name
        $this->assertStringContainsString('Free', $instructions);          // default price 0
    }

    // ── getSectionSchema() coverage for every section type ──────────────────

    public function test_hero_section_schema(): void
    {
        $instructions = $this->makeAgent(['section' => 'hero'])->instructions();

        $this->assertStringContainsString("'hero'", $instructions);
        $this->assertStringContainsString('"headline"', $instructions);
        $this->assertStringContainsString('"subheadline"', $instructions);
        $this->assertStringContainsString('"cta_label"', $instructions);
    }

    public function test_social_proof_section_schema(): void
    {
        $instructions = $this->makeAgent(['section' => 'social_proof'])->instructions();

        $this->assertStringContainsString('"stat_label"', $instructions);
        $this->assertStringContainsString('"trust_line"', $instructions);
    }

    public function test_benefits_section_schema(): void
    {
        $instructions = $this->makeAgent(['section' => 'benefits'])->instructions();

        $this->assertStringContainsString('"items"', $instructions);
        $this->assertStringContainsString('exactly 4', $instructions);
    }

    public function test_for_you_section_schema(): void
    {
        $instructions = $this->makeAgent(['section' => 'for_you'])->instructions();

        $this->assertStringContainsString('"points"', $instructions);
        $this->assertStringContainsString('exactly 3', $instructions);
    }

    public function test_creator_section_schema(): void
    {
        $instructions = $this->makeAgent(['section' => 'creator'])->instructions();

        $this->assertStringContainsString('"bio"', $instructions);
        $this->assertStringContainsString('3rd person', $instructions);
    }

    public function test_testimonials_section_schema(): void
    {
        $instructions = $this->makeAgent(['section' => 'testimonials'])->instructions();

        $this->assertStringContainsString('"quote"', $instructions);
        $this->assertStringContainsString('exactly 3', $instructions);
    }

    public function test_faq_section_schema(): void
    {
        $instructions = $this->makeAgent(['section' => 'faq'])->instructions();

        $this->assertStringContainsString('"question"', $instructions);
        $this->assertStringContainsString('"answer"', $instructions);
        $this->assertStringContainsString('exactly 4', $instructions);
    }

    public function test_cta_section_schema(): void
    {
        $instructions = $this->makeAgent(['section' => 'cta_section'])->instructions();

        $this->assertStringContainsString('"headline"', $instructions);
        $this->assertStringContainsString('"subtext"', $instructions);
        $this->assertStringContainsString('"cta_label"', $instructions);
    }

    public function test_offer_stack_section_schema_with_paid_price(): void
    {
        $instructions = $this->makeAgent([
            'section' => 'offer_stack',
            'price' => 999,
            'currency' => 'USD',
        ])->instructions();

        $this->assertStringContainsString('"total_value"', $instructions);
        $this->assertStringContainsString('"price_note"', $instructions);
        $this->assertStringContainsString('USD 999', $instructions);
    }

    public function test_offer_stack_section_schema_with_free_price(): void
    {
        $instructions = $this->makeAgent([
            'section' => 'offer_stack',
            'price' => 0,
            'currency' => 'PHP',
        ])->instructions();

        // When price is 0, the example should use "PHP 999" as the fallback example
        $this->assertStringContainsString('PHP 999', $instructions);
    }

    public function test_guarantee_section_schema(): void
    {
        $instructions = $this->makeAgent(['section' => 'guarantee'])->instructions();

        $this->assertStringContainsString('"days"', $instructions);
        $this->assertStringContainsString('Money-Back Guarantee', $instructions);
    }

    public function test_price_justification_section_schema(): void
    {
        $instructions = $this->makeAgent(['section' => 'price_justification'])->instructions();

        $this->assertStringContainsString('"options"', $instructions);
        $this->assertStringContainsString('The Smart Choice', $instructions);
    }

    public function test_unknown_section_returns_empty_object_schema(): void
    {
        $instructions = $this->makeAgent(['section' => 'nonexistent'])->instructions();

        $this->assertStringContainsString('{}', $instructions);
    }
}
