<?php

namespace Tests\Feature\Actions\Classroom;

use App\Actions\Classroom\GenerateCourseDescription;
use App\Exceptions\AiBudgetExceededException;
use App\Models\AiUsageLog;
use App\Models\Community;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class GenerateCourseDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private function actionWith(string $text): GenerateCourseDescription
    {
        return new class($text) extends GenerateCourseDescription
        {
            public function __construct(private string $stubText) {}

            protected function makeAgent(Community $community, Course $course): object
            {
                $text = $this->stubText;

                return new class($text)
                {
                    public function __construct(private string $text) {}

                    public function prompt($message)
                    {
                        return (object) ['text' => $this->text];
                    }
                };
            }
        };
    }

    private function actionWithThrowingAgent(): GenerateCourseDescription
    {
        return new class extends GenerateCourseDescription
        {
            protected function makeAgent(Community $community, Course $course): object
            {
                return new class
                {
                    public function prompt($message)
                    {
                        throw new RuntimeException('LLM down');
                    }
                };
            }
        };
    }

    public function test_returns_trimmed_description_from_agent(): void
    {
        $community = Community::factory()->create();
        $course = Course::factory()->create(['community_id' => $community->id]);

        $result = $this->actionWith("  Launch your first product in 30 days.  \n")
            ->execute($community, $course, userId: 1);

        $this->assertSame('Launch your first product in 30 days.', $result);
    }

    public function test_strips_surrounding_quotes_and_code_fences(): void
    {
        $community = Community::factory()->create();
        $course = Course::factory()->create(['community_id' => $community->id]);

        $wrapped = "\"Master funnels in a weekend.\"";
        $result = $this->actionWith($wrapped)
            ->execute($community, $course, userId: null);
        $this->assertSame('Master funnels in a weekend.', $result);

        $fenced = "```\nMaster funnels in a weekend.\n```";
        $result = $this->actionWith($fenced)
            ->execute($community, $course, userId: null);
        $this->assertSame('Master funnels in a weekend.', $result);
    }

    public function test_throws_on_empty_response(): void
    {
        $community = Community::factory()->create();
        $course = Course::factory()->create(['community_id' => $community->id]);

        $this->expectException(RuntimeException::class);

        $this->actionWith('   ')->execute($community, $course, userId: null);
    }

    public function test_does_not_persist_description_to_course(): void
    {
        $community = Community::factory()->create();
        $course = Course::factory()->create([
            'community_id' => $community->id,
            'description' => 'Original description',
        ]);

        $this->actionWith('Brand new AI description.')
            ->execute($community, $course, userId: null);

        $this->assertSame('Original description', $course->fresh()->description);
    }

    public function test_agent_errors_bubble_up(): void
    {
        $community = Community::factory()->create();
        $course = Course::factory()->create(['community_id' => $community->id]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('LLM down');

        $this->actionWithThrowingAgent()->execute($community, $course, userId: null);
    }

    public function test_throws_when_community_budget_exceeded(): void
    {
        config()->set('ai_budgets.hard_caps.enabled', true);
        config()->set('ai_budgets.hard_caps.max_usd_per_community', 1.00);

        $community = Community::factory()->create();
        $course = Course::factory()->create(['community_id' => $community->id]);

        AiUsageLog::create([
            'community_id' => $community->id,
            'kind' => 'text',
            'provider' => 'fake',
            'model' => 'fake',
            'prompt_tokens' => 0,
            'completion_tokens' => 0,
            'cost_usd' => 5.00,
            'created_at' => now(),
        ]);

        $this->expectException(AiBudgetExceededException::class);

        $this->actionWith('ignored')->execute($community, $course, userId: null);
    }
}
