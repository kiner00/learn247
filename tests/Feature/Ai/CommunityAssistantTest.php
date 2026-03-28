<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\CommunityAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommunityAssistantTest extends TestCase
{
    use RefreshDatabase;

    private function baseContext(array $overrides = []): array
    {
        return array_merge([
            'id'          => 1,
            'name'        => 'Test User',
            'email'       => 'test@example.com',
            'communities' => [],
        ], $overrides);
    }

    public function test_instructions_include_user_info(): void
    {
        $agent = new CommunityAssistant($this->baseContext());

        $instructions = $agent->instructions();

        $this->assertStringContainsString('Test User', $instructions);
        $this->assertStringContainsString('test@example.com', $instructions);
    }

    public function test_instructions_include_user_id(): void
    {
        $agent = new CommunityAssistant($this->baseContext(['id' => 42]));

        $instructions = $agent->instructions();

        $this->assertStringContainsString('ID: 42', $instructions);
    }

    public function test_instructions_include_curzzo_identity(): void
    {
        $agent = new CommunityAssistant($this->baseContext());

        $instructions = $agent->instructions();

        $this->assertStringContainsString('Curzzo', $instructions);
    }

    public function test_instructions_include_today_date(): void
    {
        $agent = new CommunityAssistant($this->baseContext());

        $instructions = $agent->instructions();

        $this->assertStringContainsString(now()->toFormattedDateString(), $instructions);
    }

    public function test_instructions_include_platform_rules(): void
    {
        $agent = new CommunityAssistant($this->baseContext());

        $instructions = $agent->instructions();

        $this->assertStringContainsString('RULES', $instructions);
        $this->assertStringContainsString('tools', $instructions);
    }

    public function test_tools_include_community_tools(): void
    {
        $agent = new CommunityAssistant($this->baseContext(['id' => 5]));

        $tools = iterator_to_array($agent->tools());

        $this->assertNotEmpty($tools);
    }
}
