<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\RegenerateLandingSection;
use App\Ai\Agents\LandingPageSectionBuilder;
use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegenerateLandingSectionTest extends TestCase
{
    use RefreshDatabase;

    public function test_execute_regenerates_hero_section(): void
    {
        $heroData = json_encode([
            'headline'    => 'New Bold Headline',
            'subheadline' => 'New subheadline',
            'cta_label'   => 'Join Now',
        ]);

        LandingPageSectionBuilder::fake([$heroData]);

        $user      = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $user->id,
            'landing_page' => ['hero' => ['headline' => 'Old']],
        ]);

        $action = app(RegenerateLandingSection::class);
        $result = $action->execute($community, 'hero');

        $this->assertEquals('hero', $result['section']);
        $this->assertEquals('New Bold Headline', $result['data']['headline']);

        // Verify saved to DB
        $community->refresh();
        $this->assertEquals('New Bold Headline', $community->landing_page['hero']['headline']);
    }

    public function test_execute_throws_on_invalid_section(): void
    {
        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid section: bogus');

        $action = app(RegenerateLandingSection::class);
        $action->execute($community, 'bogus');
    }

    public function test_execute_throws_on_invalid_json_from_ai(): void
    {
        LandingPageSectionBuilder::fake(['not valid json']);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AI returned invalid JSON');

        $action = app(RegenerateLandingSection::class);
        $action->execute($community, 'faq');
    }

    public function test_execute_strips_code_fences(): void
    {
        $faqData = json_encode([
            ['question' => 'Q1', 'answer' => 'A1'],
            ['question' => 'Q2', 'answer' => 'A2'],
        ]);

        LandingPageSectionBuilder::fake(["```json\n{$faqData}\n```"]);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        $action = app(RegenerateLandingSection::class);
        $result = $action->execute($community, 'faq');

        $this->assertEquals('faq', $result['section']);
        $this->assertCount(2, $result['data']);
    }

    public function test_execute_merges_into_existing_landing_page(): void
    {
        $benefitsData = json_encode([
            'headline' => 'Why Join',
            'items'    => [],
        ]);

        LandingPageSectionBuilder::fake([$benefitsData]);

        $user      = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $user->id,
            'landing_page' => [
                'hero' => ['headline' => 'Keep this'],
                'benefits' => ['headline' => 'Old benefits'],
            ],
        ]);

        $action = app(RegenerateLandingSection::class);
        $action->execute($community, 'benefits');

        $community->refresh();
        // hero preserved
        $this->assertEquals('Keep this', $community->landing_page['hero']['headline']);
        // benefits replaced
        $this->assertEquals('Why Join', $community->landing_page['benefits']['headline']);
    }

    public function test_execute_handles_null_landing_page(): void
    {
        $heroData = json_encode(['headline' => 'Fresh', 'subheadline' => 'Start', 'cta_label' => 'Go']);

        LandingPageSectionBuilder::fake([$heroData]);

        $user      = User::factory()->create();
        $community = Community::factory()->create([
            'owner_id'     => $user->id,
            'landing_page' => null,
        ]);

        $action = app(RegenerateLandingSection::class);
        $result = $action->execute($community, 'hero');

        $this->assertEquals('hero', $result['section']);
        $community->refresh();
        $this->assertNotNull($community->landing_page);
    }

    /**
     * @dataProvider validSectionsProvider
     */
    public function test_all_valid_sections_are_accepted(string $section): void
    {
        LandingPageSectionBuilder::fake([json_encode(['test' => true])]);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        $action = app(RegenerateLandingSection::class);
        $result = $action->execute($community, $section);

        $this->assertEquals($section, $result['section']);
    }

    public static function validSectionsProvider(): array
    {
        return [
            'hero'                => ['hero'],
            'social_proof'        => ['social_proof'],
            'benefits'            => ['benefits'],
            'for_you'             => ['for_you'],
            'creator'             => ['creator'],
            'testimonials'        => ['testimonials'],
            'faq'                 => ['faq'],
            'cta_section'         => ['cta_section'],
            'offer_stack'         => ['offer_stack'],
            'guarantee'           => ['guarantee'],
            'price_justification' => ['price_justification'],
        ];
    }
}
