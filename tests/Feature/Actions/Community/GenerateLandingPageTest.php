<?php

namespace Tests\Feature\Actions\Community;

use App\Actions\Community\GenerateLandingPage;
use App\Ai\Agents\LandingPageBuilder;
use App\Models\Community;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class GenerateLandingPageTest extends TestCase
{
    use RefreshDatabase;

    private function validLandingPageJson(): string
    {
        return json_encode([
            'hero' => [
                'headline'    => 'Transform Your Skills Today',
                'subheadline' => 'Join thousands of learners',
                'cta_label'   => 'Join Now',
            ],
            'social_proof' => [
                'stat_label' => '500+ members and growing',
                'trust_line' => 'Trusted by top professionals',
            ],
            'benefits' => [
                'headline' => 'Why Join Us',
                'items'    => [
                    ['icon' => '🚀', 'title' => 'Fast', 'body' => 'Learn fast'],
                    ['icon' => '💡', 'title' => 'Smart', 'body' => 'Learn smart'],
                    ['icon' => '🎯', 'title' => 'Focus', 'body' => 'Stay focused'],
                    ['icon' => '🏆', 'title' => 'Win', 'body' => 'Win big'],
                ],
            ],
            'for_you' => [
                'headline' => 'This is for you if...',
                'points'   => ['You want growth', 'You want community', 'You want results'],
            ],
            'creator' => [
                'headline' => 'Meet Your Coach',
                'bio'      => 'An expert in the field.',
            ],
            'testimonials' => [
                ['name' => 'Alice', 'role' => 'Developer', 'quote' => 'Amazing community!'],
                ['name' => 'Bob',   'role' => 'Designer',  'quote' => 'Best investment ever!'],
                ['name' => 'Carol', 'role' => 'Manager',   'quote' => 'Highly recommend!'],
            ],
            'faq' => [
                ['question' => 'How does it work?', 'answer' => 'Simple signup.'],
                ['question' => 'Is there a refund?', 'answer' => 'Yes, 30 days.'],
                ['question' => 'What do I get?',    'answer' => 'Full access.'],
                ['question' => 'Who is this for?',  'answer' => 'Everyone.'],
            ],
            'cta_section' => [
                'headline'  => 'Ready to Start?',
                'subtext'   => 'No risk, cancel anytime.',
                'cta_label' => 'Get Started',
            ],
        ]);
    }

    public function test_execute_generates_landing_page_and_saves_to_community(): void
    {
        LandingPageBuilder::fake([$this->validLandingPageJson()]);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id, 'category' => 'Tech']);

        $action = app(GenerateLandingPage::class);
        $result = $action->execute($community, $user);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('hero', $result);
        $this->assertArrayHasKey('benefits', $result);
        $this->assertArrayHasKey('faq', $result);
        $this->assertArrayHasKey('_sections', $result);

        // Verify _sections structure
        $sectionTypes = array_column($result['_sections'], 'type');
        $this->assertContains('hero', $sectionTypes);
        $this->assertContains('cta_section', $sectionTypes);
        $this->assertContains('offer_stack', $sectionTypes);

        // Verify saved to DB
        $community->refresh();
        $this->assertNotNull($community->landing_page);
        $this->assertEquals($result, $community->landing_page);

        LandingPageBuilder::assertPrompted(fn () => true);
    }

    public function test_execute_strips_markdown_code_fences_from_response(): void
    {
        $jsonWithFences = "```json\n" . $this->validLandingPageJson() . "\n```";
        LandingPageBuilder::fake([$jsonWithFences]);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        $action = app(GenerateLandingPage::class);
        $result = $action->execute($community, $user);

        $this->assertArrayHasKey('hero', $result);
        $this->assertArrayHasKey('benefits', $result);
    }

    public function test_execute_throws_on_invalid_json(): void
    {
        LandingPageBuilder::fake(['this is not json at all']);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        Log::shouldReceive('warning')->once();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AI returned an unexpected format');

        $action = app(GenerateLandingPage::class);
        $action->execute($community, $user);
    }

    public function test_execute_throws_when_required_keys_missing(): void
    {
        // Valid JSON but missing required keys (hero, benefits, faq)
        LandingPageBuilder::fake([json_encode(['some_key' => 'value'])]);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        Log::shouldReceive('warning')->once();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('AI returned an unexpected format');

        $action = app(GenerateLandingPage::class);
        $action->execute($community, $user);
    }

    public function test_sections_visibility_reflects_present_data(): void
    {
        // Response without social_proof, testimonials empty
        $data = json_decode($this->validLandingPageJson(), true);
        unset($data['social_proof']);
        $data['testimonials'] = [];

        LandingPageBuilder::fake([json_encode($data)]);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);

        $action = app(GenerateLandingPage::class);
        $result = $action->execute($community, $user);

        $sections = collect($result['_sections']);

        // social_proof not set -> visible false
        $this->assertFalse($sections->firstWhere('type', 'social_proof')['visible']);
        // testimonials empty -> visible false
        $this->assertFalse($sections->firstWhere('type', 'testimonials')['visible']);
        // hero always visible
        $this->assertTrue($sections->firstWhere('type', 'hero')['visible']);
        // offer_stack always false
        $this->assertFalse($sections->firstWhere('type', 'offer_stack')['visible']);
        // guarantee always false
        $this->assertFalse($sections->firstWhere('type', 'guarantee')['visible']);
    }

    public function test_execute_uses_members_count_attribute_when_loaded(): void
    {
        LandingPageBuilder::fake([$this->validLandingPageJson()]);

        $user      = User::factory()->create();
        $community = Community::factory()->create(['owner_id' => $user->id]);
        // Preload members_count
        $community->loadCount('members');

        $action = app(GenerateLandingPage::class);
        $result = $action->execute($community, $user);

        $this->assertArrayHasKey('hero', $result);
    }
}
