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

    public function test_instructions_include_community_lesson_stats(): void
    {
        $context = $this->baseContext([
            'communities' => [[
                'name'                  => 'Laravel Pro',
                'role'                  => 'member',
                'level'                 => 3,
                'points'                => 150,
                'lessons_done'          => 5,
                'lessons_total'         => 10,
                'lessons_pending_names' => [],
                'quizzes'               => [],
                'badges'                => [],
            ]],
        ]);

        $agent = new CommunityAssistant($context);
        $instructions = $agent->instructions();

        $this->assertStringContainsString('Laravel Pro', $instructions);
        $this->assertStringContainsString('5 / 10', $instructions);
    }

    public function test_instructions_include_pending_lesson_names(): void
    {
        $context = $this->baseContext([
            'communities' => [[
                'name'                  => 'My Community',
                'role'                  => 'member',
                'level'                 => 1,
                'points'                => 0,
                'lessons_done'          => 0,
                'lessons_total'         => 2,
                'lessons_pending_names' => ['Intro to PHP', 'Advanced OOP'],
                'quizzes'               => [],
                'badges'                => [],
            ]],
        ]);

        $agent = new CommunityAssistant($context);
        $instructions = $agent->instructions();

        $this->assertStringContainsString('Pending lessons: Intro to PHP, Advanced OOP', $instructions);
    }

    public function test_instructions_include_quiz_statuses(): void
    {
        $context = $this->baseContext([
            'communities' => [[
                'name'                  => 'Quiz Community',
                'role'                  => 'member',
                'level'                 => 2,
                'points'                => 50,
                'lessons_done'          => 3,
                'lessons_total'         => 5,
                'lessons_pending_names' => [],
                'quizzes'               => [
                    ['title' => 'PHP Basics', 'passed' => true, 'attempted' => true, 'score' => 90],
                    ['title' => 'OOP Quiz', 'passed' => false, 'attempted' => true, 'score' => 40],
                    ['title' => 'Design Patterns', 'passed' => false, 'attempted' => false, 'score' => 0],
                ],
                'badges' => [],
            ]],
        ]);

        $agent = new CommunityAssistant($context);
        $instructions = $agent->instructions();

        $this->assertStringContainsString('Quiz "PHP Basics": PASSED (90%)', $instructions);
        $this->assertStringContainsString('Quiz "OOP Quiz": FAILED (40%) — retake available', $instructions);
        $this->assertStringContainsString('Quiz "Design Patterns": NOT ATTEMPTED', $instructions);
    }

    public function test_instructions_include_earned_badges(): void
    {
        $context = $this->baseContext([
            'communities' => [[
                'name'                  => 'Badge Community',
                'role'                  => 'admin',
                'level'                 => 5,
                'points'                => 1000,
                'lessons_done'          => 10,
                'lessons_total'         => 10,
                'lessons_pending_names' => [],
                'quizzes'               => [],
                'badges'                => ['First Post', 'Quiz Master'],
            ]],
        ]);

        $agent = new CommunityAssistant($context);
        $instructions = $agent->instructions();

        $this->assertStringContainsString('Badges earned: First Post, Quiz Master', $instructions);
    }

    public function test_instructions_show_no_badges_message_when_empty(): void
    {
        $context = $this->baseContext([
            'communities' => [[
                'name'                  => 'Empty Badge Community',
                'role'                  => 'member',
                'level'                 => 1,
                'points'                => 0,
                'lessons_done'          => 0,
                'lessons_total'         => 5,
                'lessons_pending_names' => [],
                'quizzes'               => [],
                'badges'                => [],
            ]],
        ]);

        $agent = new CommunityAssistant($context);
        $instructions = $agent->instructions();

        $this->assertStringContainsString('Badges earned: none yet', $instructions);
    }

    public function test_instructions_omit_pending_lessons_line_when_empty(): void
    {
        $context = $this->baseContext([
            'communities' => [[
                'name'                  => 'Done Community',
                'role'                  => 'member',
                'level'                 => 1,
                'points'                => 100,
                'lessons_done'          => 5,
                'lessons_total'         => 5,
                'lessons_pending_names' => [],
                'quizzes'               => [],
                'badges'                => [],
            ]],
        ]);

        $agent = new CommunityAssistant($context);
        $instructions = $agent->instructions();

        $this->assertStringNotContainsString('Pending lessons:', $instructions);
    }

    public function test_instructions_omit_quiz_section_when_empty(): void
    {
        $context = $this->baseContext([
            'communities' => [[
                'name'                  => 'No Quiz Community',
                'role'                  => 'member',
                'level'                 => 1,
                'points'                => 0,
                'lessons_done'          => 0,
                'lessons_total'         => 0,
                'lessons_pending_names' => [],
                'quizzes'               => [],
                'badges'                => [],
            ]],
        ]);

        $agent = new CommunityAssistant($context);
        $instructions = $agent->instructions();

        $this->assertStringNotContainsString('Quiz "', $instructions);
    }
}
